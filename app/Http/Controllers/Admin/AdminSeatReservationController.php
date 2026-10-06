<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Services\EvolutionWhatsAppService;
use App\Services\TicketImageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSeatReservationController extends Controller
{
    /**
     * List every trip the admin can apartar seats for, with a quick
     * "pending / sent" counter so they can spot trips that still have
     * unpaid reservations to chase down.
     */
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->withCount([
                // Counter rows are filtered to admin-created apartados only
                // (have customer_name or customer_email) so legacy
                // client-purchase reservations don't pollute the dashboard.
                'seatReservations as pending_reservations_count' => fn ($q) => $q
                    ->where('status', SeatReservation::STATUS_PENDING)
                    ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email')),
                'seatReservations as sent_reservations_count' => fn ($q) => $q
                    ->where('status', SeatReservation::STATUS_SENT)
                    ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email')),
            ])
            ->where('is_active', true)
            ->orderBy('day')
            ->orderBy('departure_time')
            ->get();

        return view('admin.asientos.index', [
            'routes' => $routes,
        ]);
    }

    /**
     * Seat picker for the admin. Same Konva canvas the customer sees on
     * /viajes/{trip}/asientos, but with three differences:
     *   1. The legend marks "pending" (orange) and "sent" (blue)
     *      alongside the normal available / sold colors.
     *   2. There's a form on the side to type the client's name/email
     *      and turn a multi-seat selection into a pending apartado.
     *   3. There's a "Apartados pendientes" panel below that lists
     *      every pending reservation with its "Enviar boleto" button.
     */
    public function show(LandingRoute $landingRoute): View
    {
        abort_unless($landingRoute->hasSeatMap(), 404, 'Este viaje no tiene un mapa de asientos configurado.');

        $landingRoute->load('busUnit.seats', 'prices');

        // Only admin-created apartados belong on this screen — we filter
        // by customer_name/email being set so legacy client-purchase
        // reservations (which carry a user_id but no customer_name and
        // default to status='pending' after the migration) don't show up
        // here as fake "pendientes". Those are tracked separately as
        // takenIds below.
        $apartadoScope = fn ($q) => $q
            ->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
            ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email'));

        // A multi-seat apartado links its seats via notes = "group:{root_id}"
        // (same convention the online-purchase flow uses) so the whole
        // batch can be shown as ONE card and sent as ONE combined
        // WhatsApp/email message instead of one per seat. A root is
        // whatever ISN'T that group-link marker — either null, or a real
        // free-text admin note on a single-seat apartado.
        $rootScope = fn ($q) => $q->where(fn ($q3) => $q3->whereNull('notes')->orWhere('notes', 'not like', 'group:%'));

        // Lightweight, unpaginated pass used only to color the seat map
        // and total the pending/enviado badges — the "Apartados" list
        // below is paginated separately so a trip with months of history
        // doesn't load (or render) hundreds of cards at once.
        $allReservations = $apartadoScope($landingRoute->seatReservations())->get(['id', 'bus_unit_seat_id', 'status', 'notes']);
        $reservationsBySeat = $allReservations->groupBy('bus_unit_seat_id');
        $roots = $allReservations->filter(fn (SeatReservation $r) => $r->notes === null || ! str_starts_with((string) $r->notes, 'group:'));
        $pendingCount = $roots->where('status', SeatReservation::STATUS_PENDING)->count();
        $sentCount = $roots->where('status', SeatReservation::STATUS_SENT)->count();

        $reservations = $rootScope($apartadoScope($landingRoute->seatReservations()))
            ->with(['reservedBy'])
            ->orderBy('created_at')
            ->paginate(12);

        // Attach each root's other seats (for display only — "3 asientos:
        // A1, A2, A3") without an extra query per row.
        $groupSeatsByNote = $apartadoScope($landingRoute->seatReservations())
            ->whereIn('notes', $reservations->map(fn (SeatReservation $r) => 'group:'.$r->id))
            ->with('seat')
            ->get()
            ->groupBy('notes');
        $reservations->getCollection()->each(function (SeatReservation $r) use ($groupSeatsByNote) {
            $r->setRelation('groupSeats', $groupSeatsByNote->get('group:'.$r->id, collect()));
        });

        // Real client purchases (no customer_name/email, but a user_id):
        // these are seats that are paid-for and shouldn't be re-apartable.
        $takenIds = $landingRoute->seatReservations()
            ->whereNull('customer_name')
            ->whereNull('customer_email')
            ->whereNotNull('user_id')
            ->pluck('bus_unit_seat_id');

        return view('admin.asientos.show', [
            'trip' => $landingRoute,
            'reservations' => $reservations,
            'reservationsBySeat' => $reservationsBySeat,
            'pendingCount' => $pendingCount,
            'sentCount' => $sentCount,
            'takenIds' => $takenIds,
        ]);
    }

    /**
     * Persist a new apartado. Each selected seat becomes its own
     * SeatReservation row (one per seat, not one per client) so the
     * existing client-purchase flow keeps working without changes:
     * apartados just add pending rows on top, and the same "is this
     * seat taken?" query already filters them out. The ticket is sent
     * by WhatsApp automatically right here — the admin doesn't have to
     * also click "Enviar boleto" afterward; that button only remains as
     * a manual retry for when the automatic send fails (WhatsApp not
     * configured/connected, a transient API error, etc.).
     */
    public function store(Request $request, LandingRoute $landingRoute, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        if ($landingRoute->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se pueden crear apartados.');
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            // Paid status and payment method are independent now: "paid"
            // (sí/no) decides whether the real QR ticket ships right away
            // or the apartado stays pending; "payment_method" records how
            // it was/will be paid (transferencia/tarjeta/efectivo) either
            // way, same as the online-purchase flow tracks it.
            'paid' => ['required', 'boolean'],
            'payment_method' => ['required', 'string', 'in:transfer,card,cash'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $landingRoute->bus_unit_id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tripType = $data['trip_type'];
        $isPaid = (bool) $data['paid'];
        $paymentMethod = $data['payment_method'];
        $isCashPending = ! $isPaid;
        $unitPrice = (float) ($landingRoute->priceFor($tripType)?->price ?? 0);

        // Unpaid-by-transfer apartados get the same reference-number
        // workflow as an online transfer purchase, so they can be
        // validated from the exact same /admin/pagos screen.
        $transferReference = null;
        if ($isCashPending && $paymentMethod === SeatReservation::PAYMENT_METHOD_TRANSFER) {
            $transferReference = SeatReservation::generateTransferReference(
                $landingRoute->day ?? now(),
                count($data['seat_ids']),
                $data['customer_name']
            );
        }

        // Reject seats that are already taken by either a confirmed
        // client purchase (user_id set) or another active apartado
        // (pending/sent). A double-booking would surface in the picker
        // as soon as both admins refreshed, so we block it here while
        // the form was still open.
        $alreadyTaken = $landingRoute->seatReservations()
            ->whereIn('bus_unit_seat_id', $data['seat_ids'])
            ->where(function ($q) {
                $q->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
                    ->orWhereNotNull('user_id');
            })
            ->pluck('bus_unit_seat_id')
            ->all();

        if (! empty($alreadyTaken)) {
            $labels = BusUnitSeat::whereIn('id', $alreadyTaken)->pluck('label')->all();

            return back()
                ->withInput()
                ->with('error', 'Los siguientes asientos ya están apartados: '.implode(', ', $labels));
        }

        // Server-side check for the per-seat trip-type restriction —
        // mirrors what the JS already does on the picker so a hand-crafted
        // POST can't sneak through. "one_way" and "especial" can use any
        // bookable seat, no allowed_trip_type/zone restriction applies.
        $mismatched = $landingRoute->busUnit->seats()
            ->whereIn('id', $data['seat_ids'])
            ->get()
            ->filter(fn ($seat) => ! in_array($tripType, [TripTicketPrice::TYPE_ONE_WAY, TripTicketPrice::TYPE_ESPECIAL], true)
                && ! $seat->allowsTripType($tripType))
            ->pluck('label')
            ->all();

        if (! empty($mismatched)) {
            return back()
                ->withInput()
                ->with('error', 'Estos asientos no están disponibles para el tipo de viaje seleccionado: '.implode(', ', $mismatched));
        }

        // Multiple seats from the same submission are linked into one
        // apartado group — same "notes = group:{root_id}" convention the
        // online-purchase flow already uses (see SeatReservation::sendGroupTickets()).
        // The root (first seat) carries the admin's free-text note; the
        // rest just carry the group link. This is what lets the WhatsApp
        // send below go out as ONE image instead of one per seat.
        $root = DB::transaction(function () use ($landingRoute, $data, $request, $tripType, $unitPrice, $isCashPending, $paymentMethod, $transferReference) {
            $root = null;
            foreach ($data['seat_ids'] as $seatId) {
                $new = SeatReservation::create([
                    'landing_route_id' => $landingRoute->id,
                    'bus_unit_seat_id' => $seatId,
                    'user_id' => null,
                    'trip_type' => $tripType,
                    'leg' => $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND,
                    'unit_price' => $unitPrice,
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'customer_phone' => $data['customer_phone'],
                    'status' => SeatReservation::STATUS_PENDING,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $isCashPending ? SeatReservation::PAYMENT_PENDING : SeatReservation::PAYMENT_COMPLETED,
                    'paid_at' => $isCashPending ? null : now(),
                    // transfer_reference is DB-unique, and only the ROOT
                    // row of a multi-seat group should carry it anyway
                    // (same convention as the online purchase flow) —
                    // every other seat just links back via "notes".
                    'transfer_reference' => $root ? null : $transferReference,
                    'transfer_expires_at' => ! $root && $transferReference ? now()->addDays(3) : null,
                    'reserved_by' => $request->user()?->id,
                    'notes' => $root ? 'group:'.$root->id : ($data['notes'] ?? null),
                ]);
                $root ??= $new;
            }

            return $root;
        });

        $group = $root->groupMembers()->load(['landingRoute', 'seat']);

        // Unpaid apartados don't get the QR-bearing ticket image yet (the
        // QR is only generated once an admin later confirms the payment
        // in /admin/pagos — see AdminPaymentController::confirmCash() /
        // validateTransfer()) — they just get a plain-text WhatsApp
        // notice saying "your seat is reserved, pay [method] to confirm".
        if ($isCashPending) {
            $noticeSent = $this->sendReservationNoticeViaWhatsApp($group, $whatsapp);

            $seatCount = count($data['seat_ids']);
            $tripTypeLabel = TripTicketPrice::tripTypes()[$tripType] ?? $tripType;
            $plural = $seatCount > 1 ? 's' : '';
            $methodLabel = $root->payment_method_label;
            $activationHint = $paymentMethod === SeatReservation::PAYMENT_METHOD_TRANSFER
                ? 'Valida la transferencia en Pagos para mandar el boleto con QR.'
                : 'El boleto se imprime/envía cuando confirmes el pago en Pagos.';

            if ($noticeSent) {
                return redirect()
                    ->route('admin.asientos.show', $landingRoute)
                    ->with('success', "Apartado creado para {$data['customer_name']} ({$seatCount} asiento{$plural}, {$tripTypeLabel}, {$methodLabel}). El cliente recibirá un aviso por WhatsApp. {$activationHint}");
            }

            return redirect()
                ->route('admin.asientos.show', $landingRoute)
                ->with('error', "Apartado creado para {$data['customer_name']} ({$seatCount} asiento{$plural}, {$tripTypeLabel}, {$methodLabel}), pero no se pudo enviar el aviso por WhatsApp automáticamente. {$activationHint}");
        }

        $sent = $this->sendGroupViaWhatsApp($group, $whatsapp, $ticketImages);

        if ($sent) {
            SeatReservation::whereIn('id', $group->pluck('id'))->update([
                'status' => SeatReservation::STATUS_SENT,
                'ticket_sent_at' => now(),
            ]);
        }

        $seatCount = count($data['seat_ids']);
        $tripTypeLabel = TripTicketPrice::tripTypes()[$tripType] ?? $tripType;
        $plural = $seatCount > 1 ? 's' : '';

        if ($sent) {
            return redirect()
                ->route('admin.asientos.show', $landingRoute)
                ->with('success', "Apartado creado y boleto{$plural} enviado{$plural} por WhatsApp a {$data['customer_name']} ({$seatCount} asiento{$plural}, {$tripTypeLabel}).");
        }

        return redirect()
            ->route('admin.asientos.show', $landingRoute)
            ->with('error', "Apartado creado para {$data['customer_name']} ({$seatCount} asiento{$plural}, {$tripTypeLabel}), pero no se pudo enviar el boleto por WhatsApp automáticamente. Usa el botón \"Enviar boleto\" para reintentar.");
    }

    /**
     * Send the plain-text "your seat(s) are reserved" notice for an
     * unpaid apartado — covers the whole group (every seat, e.g. both
     * halves of a mancuerna), not just the root row. Same failure
     * semantics as sendGroupViaWhatsApp() — caller decides what to do
     * when it returns false.
     */
    private function sendReservationNoticeViaWhatsApp(Collection $group, EvolutionWhatsAppService $whatsapp): bool
    {
        if (! $whatsapp->isConfigured()) {
            return false;
        }

        try {
            $whatsapp->sendReservationNotice($group);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Apartado cash-pending WhatsApp notice failed for reservation '.$group->first()->id.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Manual retry for when the automatic WhatsApp send in store() didn't
     * go through (WhatsApp unconfigured/disconnected, a transient API
     * error, etc.) — this button is the fallback, not the primary path.
     */
    public function sendTicket(LandingRoute $landingRoute, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if ($landingRoute->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se pueden enviar boletos.');
        }

        if (! $reservation->isPending()) {
            return back()->with('error', 'Este apartado ya no está pendiente.');
        }

        $group = $reservation->groupMembers()->load(['landingRoute', 'seat'])->filter->isPending()->values();
        if ($group->isEmpty()) {
            return back()->with('error', 'Este apartado ya no está pendiente.');
        }

        $sent = $this->sendGroupViaWhatsApp($group, $whatsapp, $ticketImages);

        if ($sent) {
            SeatReservation::whereIn('id', $group->pluck('id'))->update([
                'status' => SeatReservation::STATUS_SENT,
                'ticket_sent_at' => now(),
            ]);

            return back()->with('success', ($group->count() > 1 ? 'Boletos enviados' : 'Boleto enviado').' por WhatsApp.');
        }

        return back()->with('error', $whatsapp->isConfigured()
            ? 'No se pudo enviar por WhatsApp — revisa el log.'
            : 'WhatsApp no está configurado — ve a Administración → WhatsApp.');
    }

    /**
     * Sends the whole group as ONE WhatsApp message: the original
     * single-QR flow for one seat, or TicketImageService's combined
     * multi-ticket image for several. Returns whether it actually went
     * out — callers decide what to do on failure (leave the apartado
     * pending so this same logic can be retried from the "Enviar
     * boleto" button).
     */
    private function sendGroupViaWhatsApp(Collection $group, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): bool
    {
        if (! $whatsapp->isConfigured()) {
            return false;
        }

        $first = $group->first();

        if ($group->count() === 1) {
            try {
                $whatsapp->sendTicket($first);

                return true;
            } catch (\Throwable $e) {
                Log::warning('Apartado WhatsApp send failed for reservation '.$first->id.': '.$e->getMessage());

                return false;
            }
        }

        $imagePath = null;

        try {
            $imagePath = $ticketImages->buildCombinedImage($group);
            $whatsapp->sendImageFile(
                $first->customer_phone,
                $imagePath,
                $this->buildGroupCaption($group),
                'boletos-merlo-'.$first->id.'.jpg'
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Combined apartado WhatsApp send failed for group '.$first->id.': '.$e->getMessage());

            return false;
        } finally {
            if ($imagePath && file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
    }

    /**
     * Cancel / delete a whole apartado group (every seat the admin
     * selected together for this customer). Frees the seats back into
     * the available pool so another customer (or another apartado) can
     * take them.
     */
    public function destroy(LandingRoute $landingRoute, SeatReservation $reservation): RedirectResponse
    {
        $trip = $reservation->landingRoute;

        if ($trip->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se puede cancelar este apartado.');
        }

        $group = $reservation->groupMembers();

        SeatReservation::whereIn('id', $group->pluck('id'))->delete();

        return redirect()
            ->route('admin.asientos.show', $trip)
            ->with('success', $group->count() > 1
                ? 'Apartado cancelado ('.$group->count().' asientos). Los asientos vuelven a estar disponibles.'
                : 'Apartado cancelado. El asiento vuelve a estar disponible.');
    }

    /**
     * Printable passenger manifest for a trip — every occupied seat,
     * whether it was apartado'd by an admin or bought by the customer
     * directly online, in one list so staff can check names off at
     * boarding. Excludes only rows that never actually took the seat
     * (a failed/refunded/charged-back online payment) — everything
     * else, admin apartado or real purchase, currently or eventually
     * occupies that seat per the same rule the rest of the app uses.
     */
    public function manifest(LandingRoute $landingRoute): Response
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $reservations = $landingRoute->seatReservations()
            ->where(fn ($q) => $q->whereNull('payment_status')->orWhereNotIn('payment_status', [
                SeatReservation::PAYMENT_FAILED,
                SeatReservation::PAYMENT_REFUNDED,
                SeatReservation::PAYMENT_CHARGEBACK,
            ]))
            ->with(['seat', 'user'])
            ->get()
            ->sortBy(fn (SeatReservation $r) => $r->seat?->label ?? '', SORT_NATURAL)
            ->values();

        $pdf = Pdf::loadView('admin.asientos.manifiesto-pdf', [
            'trip' => $landingRoute,
            'reservations' => $reservations,
        ]);

        return $pdf->stream('lista-asientos-'.$landingRoute->id.'.pdf');
    }

    /**
     * Edits a previously-saved apartado's category + payment state. The
     * route name stayed "update-category" for backward compatibility,
     * but the payload now covers more than the original use case —
     * the admin can correct trip_type, mark the cash as already
     * received in-window, switch a transfer to "paid" once they
     * match the bank record, etc.
     *
     * Deliberately does NOT auto-send anything via WhatsApp — the
     * "Enviar boleto(s)" button stays a separate, manual action so
     * the operator can decide when (and to whom) the ticket image
     * actually goes out.
     *
     * Blocked once the trip is over or fully checked-in, for the same
     * reporting/audit reasons as before.
     */
    public function updateCategory(Request $request, LandingRoute $landingRoute, SeatReservation $reservation): RedirectResponse
    {
        if ($landingRoute->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se puede editar el apartado.');
        }

        if ($reservation->isFullyCheckedIn()) {
            return back()->with('error', 'No se puede editar un apartado ya verificado.');
        }

        $data = $request->validate([
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            // OpenPay was removed (card/oxxo/spei are no longer offered),
            // so the only practical methods are transfer (customer paid
            // via bank transfer) and cash (ventanilla). "card" is
            // preserved as a value for legacy rows but never offered
            // on the form.
            'payment_method' => ['required', 'string', 'in:transfer,cash,card'],
            'payment_status' => ['required', 'string', 'in:pending,completed'],
        ]);

        $tripType = $data['trip_type'];
        $unitPrice = (float) ($landingRoute->priceFor($tripType)?->price ?? 0);
        $leg = $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND;

        $group = $reservation->groupMembers();
        $groupIds = $group->pluck('id');

        // Build the update payload for the whole group — every
        // propiedad a paid customer also pays the siblings, since
        // they share the same receipt.
        $update = [
            'trip_type' => $tripType,
            'leg' => $leg,
            'unit_price' => $unitPrice,
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
        ];

        // Setting payment_status=completed without a paid_at would
        // leave the column NULL and trip the "Ya está pagado?" check
        // in AdminPaymentController. Fill it the first time the
        // admin flips the toggle on; leave it alone otherwise so we
        // preserve the original timestamp.
        if ($data['payment_status'] === 'completed') {
            SeatReservation::whereIn('id', $groupIds)
                ->whereNull('paid_at')
                ->update(['paid_at' => now()]);
        }
        // Same symmetry on the "unpaid" flip: clear paid_at so
        // AdminPaymentController::validateTransfer and friends see a
        // pending reservation again.
        if ($data['payment_status'] === 'pending') {
            SeatReservation::whereIn('id', $groupIds)->update(['paid_at' => null]);
        }

        SeatReservation::whereIn('id', $groupIds)->update($update);

        $summary = collect([
            'Categoría → '.(TripTicketPrice::tripTypes()[$tripType] ?? $tripType),
            'Método → '.ucfirst($data['payment_method']),
            'Pago → '.($data['payment_status'] === 'completed' ? 'pagado' : 'pendiente'),
        ])->implode(' · ');

        return back()->with('success', 'Apartado actualizado. '.$summary.'.');
    }

    private function buildGroupCaption(Collection $reservations): string
    {
        $first = $reservations->first();
        $trip = $first->landingRoute;
        $seats = $reservations->map(fn (SeatReservation $r) => $r->seat?->label ?? '—')->implode(', ');
        // Every seat in a group apartado shares the same trip_type
        // (store() applies it uniformly), so the first reservation's
        // legend applies to the whole group.
        $legend = collect($first->boardingLegendLines())->map(fn ($line) => "📍 *{$line}*")->implode("\n");

        return "*MERLO Transportes* 🚌\n\n"
            ."Hola {$first->customer_display_name}, aquí tienen tus {$reservations->count()} boletos:\n\n"
            ."*{$trip->from} → {$trip->to}*\n"
            ."💺 Asientos: {$seats}\n\n"
            .($legend ? $legend."\n\n" : '')
            .'Todos tus códigos QR están en esta imagen — muéstrala completa al abordar.';
    }

    /**
     * "Disponibilidad" — the per-seat "this is bookable for X trip type
     * only" editor. Distinct from the per-unit editor (bus_units
     * editor) so the admin can set trip-specific rules without having
     * to dig into the unit's layout. Renders the same Konva seat map
     * with a "paint" mode UI: pick a trip type, then click seats to
     * mark them.
     */
    public function availability(LandingRoute $landingRoute): View|RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404, 'Este viaje no tiene un mapa de asientos configurado.');

        if ($landingRoute->hasEnded()) {
            return redirect()
                ->route('admin.asientos.show', $landingRoute)
                ->with('error', 'Este viaje ya pasó — ya no se puede editar la disponibilidad.');
        }

        $landingRoute->load('busUnit.seats');

        // Summary of how the seats are currently distributed across the
        // three modes, for the legend/counter at the top of the page.
        $stats = $landingRoute->busUnit->seats
            ->where('kind', 'seat')
            ->groupBy(fn ($seat) => $seat->allowed_trip_type ?? 'both')
            ->map->count();

        return view('admin.asientos.availability', [
            'trip' => $landingRoute,
            'stats' => [
                'both' => $stats['both'] ?? 0,
                'one_way' => $stats['one_way'] ?? 0,
                'round_trip' => $stats['round_trip'] ?? 0,
            ],
        ]);
    }

    /**
     * Bulk update of allowed_trip_type for the trip's seats. The client
     * sends only the seats it changed (with their new value), so the
     * payload stays small even when the trip has dozens of seats.
     */
    public function updateAvailability(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        if ($landingRoute->hasEnded()) {
            return redirect()
                ->route('admin.asientos.show', $landingRoute)
                ->with('error', 'Este viaje ya pasó — ya no se puede editar la disponibilidad.');
        }

        $data = $request->validate([
            'changes' => ['required', 'array'],
            'changes.*.id' => [
                'required', 'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $landingRoute->bus_unit_id),
            ],
            'changes.*.allowed_trip_type' => ['required', 'string', 'in:both,one_way,round_trip'],
        ]);

        $busUnitId = $landingRoute->bus_unit_id;
        $count = 0;

        DB::transaction(function () use ($data, $busUnitId, &$count) {
            foreach ($data['changes'] as $change) {
                $affected = DB::table('bus_unit_seats')
                    ->where('id', $change['id'])
                    ->where('bus_unit_id', $busUnitId)
                    ->update(['allowed_trip_type' => $change['allowed_trip_type']]);
                $count += $affected;
            }
        });

        return redirect()
            ->route('admin.asientos.availability', $landingRoute)
            ->with('success', "Disponibilidad actualizada ({$count} asiento".($count === 1 ? '' : 's').').');
    }
}
