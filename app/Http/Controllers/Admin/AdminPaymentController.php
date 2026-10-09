<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Services\EvolutionWhatsAppService;
use App\Services\OpenPayService;
use App\Services\TicketImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        // One row per PURCHASE, not per seat: a multi-seat checkout
        // creates one reservation row per seat (so each seat gets its
        // own ticket_code/QR for check-in), but they're all the same
        // payment — the "child" rows link back via notes = "group:{id}".
        // Only the root row carries the real transfer_reference/proof
        // too, so listing children here would both triple-count totals
        // and show broken-looking rows with no reference to validate.
        $query = SeatReservation::query()
            ->with(['landingRoute', 'seat', 'user'])
            ->whereNotNull('payment_method')
            ->where(function ($q) {
                $q->whereNull('notes')->orWhere('notes', 'not like', 'group:%');
            });

        if ($status = $request->query('status')) {
            $query->where('payment_status', $status);
        }
        if ($method = $request->query('method')) {
            $query->where('payment_method', $method);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('openpay_charge_id', 'like', "%{$search}%")
                    ->orWhere('ticket_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    // Guests (cash purchases especially) identify by phone
                    // instead of email, so make that searchable too —
                    // a ventanilla worker can paste the number they
                    // already have on hand.
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    // Lets an admin paste the "concepto" they read off a
                    // real bank transfer straight into the same search box
                    // to find the matching reservation.
                    ->orWhere('transfer_reference', 'like', "%{$search}%");
            });
        }

        $payments = $query->orderByDesc('id')->paginate(20)->withQueryString();

        // Attach the other seats in each purchase (for display only —
        // "3 asientos: A1, A2, A3") without an extra query per row.
        $groupSeats = SeatReservation::query()
            ->whereIn('notes', $payments->map(fn (SeatReservation $r) => 'group:'.$r->id))
            ->with('seat')
            ->get()
            ->groupBy('notes');
        $payments->getCollection()->each(function (SeatReservation $r) use ($groupSeats) {
            $r->setRelation('groupSeats', $groupSeats->get('group:'.$r->id, collect()));
        });

        $totals = [
            'completed' => (clone $query)->where('payment_status', 'completed')->sum('total'),
            'pending' => (clone $query)->where('payment_status', 'pending')->sum('total'),
            'refunded' => (clone $query)->where('payment_status', 'refunded')->sum('refund_amount'),
        ];

        return view('admin.pagos.index', [
            'payments' => $payments,
            'totals' => $totals,
            'filters' => [
                'status' => $status,
                'method' => $method,
                'q' => $search,
            ],
        ]);
    }

    public function show(SeatReservation $reservation): View|RedirectResponse
    {
        // A direct link to a "child" row (bookmarked, or an old QR) —
        // the group's real payment data (transfer_reference, proof,
        // etc.) only lives on the root, so redirect there instead of
        // showing an incomplete page.
        if (str_starts_with((string) $reservation->notes, 'group:')) {
            $rootId = (int) substr($reservation->notes, strlen('group:'));

            return redirect()->route('admin.pagos.show', $rootId);
        }

        $reservation->load(['landingRoute.busUnit', 'seat', 'user', 'outboundVerifiedBy', 'returnVerifiedBy']);

        $groupSeats = SeatReservation::query()
            ->where('notes', 'group:'.$reservation->id)
            ->with('seat')
            ->get();

        return view('admin.pagos.show', [
            'reservation' => $reservation,
            'groupSeats' => $groupSeats,
        ]);
    }

    public function refund(Request $request, SeatReservation $reservation, OpenPayService $openpay): RedirectResponse
    {
        if (! $reservation->openpay_charge_id) {
            return back()->with('error', 'Esta reservación no tiene un cargo de OpenPay asociado.');
        }
        if (! $reservation->isPaymentCompleted()) {
            return back()->with('error', 'Solo se pueden reembolsar cargos completados.');
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) ($validated['amount'] ?? $reservation->total);
        if ($amount > (float) $reservation->total) {
            return back()->with('error', 'El reembolso no puede ser mayor al cargo original.');
        }

        try {
            $refund = $openpay->refund($reservation, $amount, $validated['reason'] ?? null);
        } catch (\Throwable $e) {
            Log::error('OpenPay refund failed: '.$e->getMessage(), [
                'reservation_id' => $reservation->id,
                'charge_id' => $reservation->openpay_charge_id,
            ]);
            return back()->with('error', 'No se pudo procesar el reembolso con OpenPay: '.$e->getMessage());
        }

        $reservation->update([
            'payment_status' => SeatReservation::PAYMENT_REFUNDED,
            'refunded_at' => now(),
            'refund_amount' => $amount,
            'refund_reason' => $validated['reason'] ?? null,
            'openpay_raw_response' => json_encode(array_merge(
                json_decode($reservation->openpay_raw_response ?? '{}', true) ?: [],
                ['refund' => $refund]
            )),
        ]);

        return back()->with('status', 'Reembolso procesado correctamente.');
    }

    /**
     * Release the unused return leg of a paid round-trip reservation
     * so it can be resold to a different customer. Never automatic —
     * an admin has to know (the passenger said they're not coming
     * back, or simply didn't show up) before the seat is exposed
     * again. Opens a resale window sized by the global "vigencia"
     * setting; see SeatPickerController for how it's purchased.
     */
    public function releaseReturn(SeatReservation $reservation): RedirectResponse
    {
        if (! $reservation->isRoundTrip()) {
            return back()->with('error', 'Este boleto no es de viaje redondo.');
        }

        if (! $reservation->isPaymentCompleted()) {
            return back()->with('error', 'Solo se puede liberar el regreso de boletos pagados.');
        }

        if ($reservation->isReturnVerified()) {
            return back()->with('error', 'El regreso de este boleto ya fue utilizado.');
        }

        if ($reservation->isReturnReleased()) {
            return back()->with('error', 'El regreso de este boleto ya fue liberado.');
        }

        $hours = Setting::current()->returnResaleValidityHours();

        $reservation->update([
            'return_released_at' => now(),
            'return_released_by' => request()->user()?->id,
            'return_resale_expires_at' => now()->addHours($hours),
        ]);

        return back()->with('status', 'Regreso liberado para reventa. El asiento '.$reservation->seat?->label.' estará disponible por '.$hours.' horas.');
    }

    /**
     * Serve the customer's uploaded proof-of-payment privately — never
     * public, same disk/pattern as package photos in AdminPackageController.
     */
    public function transferProof(SeatReservation $reservation): StreamedResponse
    {
        abort_unless($reservation->transfer_proof_path && Storage::disk('local')->exists($reservation->transfer_proof_path), 404);

        return Storage::disk('local')->response($reservation->transfer_proof_path);
    }

    /**
     * Confirm a bank transfer. The admin has already found this exact
     * reservation (via the search box, matching a real transfer on
     * their bank statement) and re-pastes the reference here as a
     * fat-finger guard before it's accepted — the actual anti-fraud
     * check already happened by virtue of the reference being
     * unguessable and DB-unique.
     */
    public function validateTransfer(Request $request, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if (! $reservation->isTransfer() || ! $reservation->isPaymentPending()) {
            return back()->with('error', 'Esta reservación no tiene una transferencia pendiente de validar.');
        }

        $validated = $request->validate([
            'reference_confirm' => ['required', 'string'],
        ]);

        $typed = strtoupper(trim($validated['reference_confirm']));
        $expected = strtoupper(trim((string) $reservation->transfer_reference));

        if ($typed !== $expected) {
            return back()->with('error', 'El concepto que capturaste no coincide con la referencia de esta reservación.');
        }

        $reservation->update(['paid_at' => now()]);
        $reservation->markGroupPaid();
        $reservation->sendGroupTickets();

        // Once validated, generate the QR-bearing ticket image and ship
        // it to the customer's phone. Until this point the customer
        // only saw a transfer-receipt upload page (no QR) — the QR is
        // born here, mirroring the cash-at-window flow.
        $whatsappSent = $this->sendGroupTicketsViaWhatsApp($reservation, $whatsapp, $ticketImages);
        $this->markGroupSentIfDelivered($reservation, $whatsappSent);

        return back()->with('status', 'Transferencia validada. ' . ($whatsappSent
            ? 'El boleto se envió por correo y por WhatsApp al cliente.'
            : 'El boleto se envió por correo al cliente (WhatsApp no disponible).'));
    }

    /**
     * Reject a pending transfer (fraud suspected, or the concept simply
     * never matched a real deposit) and give the seat(s) back.
     */
    public function rejectTransfer(SeatReservation $reservation): RedirectResponse
    {
        if (! $reservation->isTransfer() || ! $reservation->isPaymentPending()) {
            return back()->with('error', 'Esta reservación no tiene una transferencia pendiente de validar.');
        }

        $reservation->releaseGroupAndFreeSeat();

        return redirect()->route('admin.pagos.index')->with('status', 'Transferencia rechazada. Los asientos vuelven a estar disponibles.');
    }

    /**
     * Ventanilla-side activation of a cash OR card apartado pending
     * payment. Mirrors validateTransfer(): the admin physically confirms
     * the payment was received, we mark the whole purchase group paid,
     * fire off the ticket by email (if there's an email), then redirect
     * to a printable view AND also push the same image to the customer's
     * WhatsApp — operators with WhatsApp configured don't have to
     * hand-deliver the image; those without can ignore the warning
     * and just print.
     */
    public function confirmCash(SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if (! $reservation->needsVentanillaActivation() || ! $reservation->isPaymentPending()) {
            return back()->with('error', 'Esta reservación no tiene un pago pendiente de confirmar en ventanilla.');
        }

        $reservation->update(['paid_at' => now()]);
        $reservation->markGroupPaid();
        $reservation->sendGroupTickets();

        $whatsappSent = $this->sendGroupTicketsViaWhatsApp($reservation, $whatsapp, $ticketImages);
        $this->markGroupSentIfDelivered($reservation, $whatsappSent);

        return redirect()
            ->route('admin.pagos.cash-ticket', $reservation)
            ->with('status', 'Pago confirmado. Imprime el boleto y entrégalo al cliente.' . ($whatsappSent
                ? ' También se envió por WhatsApp.'
                : ''));
    }

    /**
     * Printable view of the freshly-activated cash ticket. Uses the
     * same combined-image generator as the WhatsApp multi-ticket path
     * (TicketImageService::buildCombinedImage), so what the operator
     * hands the customer looks identical to what a customer who paid
     * online would have received. Cash-ticket images are written to
     * a temp file and deleted after the response — they're not
     * persisted anywhere.
     */
    public function cashTicket(SeatReservation $reservation): View
    {
        // Defensive: only show the printable for just-confirmed cash
        // tickets. A reload / direct hit on an already-printed ticket
        // shouldn't regenerate an image (and shouldn't show up in the
        // queue), so redirect back to the detail page.
        if (! $reservation->needsVentanillaActivation() || ! $reservation->isPaymentCompleted()) {
            return redirect()->route('admin.pagos.show', $reservation);
        }

        $reservation->loadMissing(['landingRoute.busUnit', 'seat']);
        $isRoot = ! str_starts_with((string) $reservation->notes, 'group:');
        $groupSeats = $isRoot
            ? SeatReservation::query()
                ->where('notes', 'group:'.$reservation->id)
                ->with('seat')
                ->get()
            : collect();

        $imageUrl = route('admin.pagos.cash-ticket-image', $reservation);

        return view('admin.pagos.cash-ticket', [
            'reservation' => $reservation,
            'groupSeats' => $groupSeats,
            'imageUrl' => $imageUrl,
        ]);
    }

    /**
     * Stream the combined ticket image (QR + fecha + leyenda de abordaje)
     * generated by TicketImageService. The cash-ticket view embeds
     * this URL in an <img>; if the operator right-clicks → save, they
     * get the same JPG file the customer would have on WhatsApp.
     */
    public function cashTicketImage(SeatReservation $reservation, TicketImageService $ticketImages): Response
    {
        abort_unless($reservation->needsVentanillaActivation() && $reservation->isPaymentCompleted(), 404);

        $group = $reservation->groupMembers()->load(['landingRoute', 'seat']);
        $path = $ticketImages->buildCombinedImage($group);

        try {
            return response()->file($path, [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline; filename="boleto-efectivo-'.$reservation->ticket_code.'.jpg"',
            ]);
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * After payment is confirmed (transfer validated or cash received),
     * build the same combined QR image that cash-ticket.blade.php shows
     * on screen and ship it to the customer's phone via Evolution API.
     * Returns true if WhatsApp was actually sent, false if it was
     * skipped because Evolution isn't configured / no phone on file /
     * upstream failure (caller can decide whether to surface a warning).
     *
     * The image is generated locally as a JPG — WhatsApp's host CDN-
     * URL approach (used by sendTicket()) only works for the single
     * per-reservation QR; multi-seat groups need a stacked image,
     * which only exists on disk and so goes via sendImageFile().
     */
    private function sendGroupTicketsViaWhatsApp(SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): bool
    {
        if (! $whatsapp->isConfigured() || empty($reservation->customer_phone)) {
            return false;
        }

        $group = $reservation->groupMembers()->load(['landingRoute', 'seat']);
        if ($group->isEmpty()) {
            return false;
        }

        $path = null;
        try {
            $path = $ticketImages->buildCombinedImage($group);
            $whatsapp->sendImageFile(
                $reservation->customer_phone,
                $path,
                $this->buildActivatedCaption($reservation, $group),
                'boleto-merlo-'.$reservation->id.'.jpg'
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp ticket image failed for reservation '.$reservation->id.': '.$e->getMessage());
            return false;
        } finally {
            if ($path && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * sendGroupTickets() only flips a row's status to SENT when it
     * actually emails it — which it skips entirely for a row with no
     * customer_email (any guest who only gave a phone number). Without
     * this, a phone-only guest whose ticket WAS delivered via WhatsApp
     * stays stuck showing "pendiente" everywhere else in the admin
     * (asientos list, re-send buttons), even though payment_status is
     * already completed and the customer already has their QR.
     */
    private function markGroupSentIfDelivered(SeatReservation $reservation, bool $whatsappSent): void
    {
        if (! $whatsappSent) {
            return;
        }

        $group = $reservation->groupMembers();
        SeatReservation::whereIn('id', $group->pluck('id'))
            ->where('status', '!=', SeatReservation::STATUS_SENT)
            ->update(['status' => SeatReservation::STATUS_SENT, 'ticket_sent_at' => now()]);
    }

    /**
     * Caption used when shipping the freshly-activated ticket image via
     * WhatsApp — same brand + boarding-point lines as the original
     * sendTicket() flow (kept in EvolutionWhatsAppService::buildCaption),
     * but tagged with the just-paid status so the customer can tell
     * apart the "apartado en espera" from the "tu boleto ya está listo".
     */
    private function buildActivatedCaption(SeatReservation $reservation, $group): string
    {
        $trip = $reservation->landingRoute;
        $tripDate = $reservation->isReturnLeg()
            ? ($trip->return_date?->toSpanishLongDate() ?? '—')
            : ($trip->day?->toSpanishLongDate() ?? '—');
        $unitName = $trip->busUnit->name ?? '—';
        $seatLabels = $group->map(fn (SeatReservation $r) => $r->seat?->label ?? '—')->implode(', ');
        $total = '$' . number_format((float) $reservation->total, 2);
        $legLabel = $reservation->needsBothLegs() ? 'salida y tu regreso' : 'subida al autobús';

        return implode("\n", [
            '*MERLO Transportes* 🚌',
            '',
            "Hola {$reservation->customer_display_name}, tu pago fue confirmado. Aquí tienes tu boleto:",
            '',
            "*{$trip->from} → {$trip->to}*",
            "📅 Salida: *{$tripDate}*",
            "🚍 Unidad: *{$unitName}*",
            "🏷️ Tipo: {$reservation->trip_type_label}",
            "💺 Asientos: {$seatLabels}",
            "💵 Total pagado: {$total} MXN",
            '',
            'Muestra el QR al abordar — el operador lo escanea para registrar tu ' . $legLabel . '.',
            'Si el QR no escanea, dicta el código:',
            '`' . $reservation->ticket_code . '`',
        ]);
    }
}
