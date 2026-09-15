<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Services\OpenPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function validateTransfer(Request $request, SeatReservation $reservation): RedirectResponse
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

        return back()->with('status', 'Transferencia validada. El boleto se envió al cliente.');
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
}
