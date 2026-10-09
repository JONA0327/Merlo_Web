<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnitSeat;
use App\Models\Customer;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\StandingReservation;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        // Catches up any "de planta" seat added/activated AFTER this trip
        // already opened (applyToTrip() otherwise only runs when the trip
        // itself is created/edited — see AdminLandingRouteController) —
        // idempotent, so revisiting this page never double-books one
        // that's already here.
        if (! $landingRoute->hasEnded()) {
            StandingReservation::applyToTrip($landingRoute);
        }

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
        $allReservations = $apartadoScope($landingRoute->seatReservations())->get(['id', 'bus_unit_seat_id', 'status', 'notes', 'trip_type']);
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

        // Seats whose round-trip/especial passenger isn't coming back
        // this day (released via AdminPaymentController::releaseReturn()
        // or AdminTripCheckinController::rescheduleReturn()) — the seat
        // picker treats these as available, but ONLY for a "regreso"
        // apartado on this same return leg, not for every trip type.
        $releasedSeatIds = $landingRoute->seatReservations()
            ->whereNotNull('return_released_at')
            ->whereNull('resold_return_reservation_id')
            ->where('return_resale_expires_at', '>', now())
            ->pluck('bus_unit_seat_id');

        // Seats with an active "de planta" assignment for this exact
        // route/bus — painted purple and blocked from manual selection on
        // the picker (see admin-seat-picker.js) so the admin sees at a
        // glance which ones auto-book themselves, and never accidentally
        // double-books one. Excludes any released "no viaja hoy" for THIS
        // trip (see releaseStanding()) — those go back to looking/acting
        // like a normal free seat for just this one trip.
        $standingSeatIds = StandingReservation::activeSeatIdsFor($landingRoute->from, $landingRoute->to, $landingRoute->bus_unit_id, $landingRoute->id);

        // Every active standing assignment for this route/bus, keyed by
        // seat — used to find the matching assignment for the "No viaja
        // hoy" button on an apartado the picker auto-booked from one.
        $standingBySeat = StandingReservation::where('from', $landingRoute->from)
            ->where('to', $landingRoute->to)
            ->where('bus_unit_id', $landingRoute->bus_unit_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('bus_unit_seat_id');

        return view('admin.asientos.show', [
            'trip' => $landingRoute,
            'reservations' => $reservations,
            'reservationsBySeat' => $reservationsBySeat,
            'pendingCount' => $pendingCount,
            'sentCount' => $sentCount,
            'takenIds' => $takenIds,
            'releasedSeatIds' => $releasedSeatIds,
            'customers' => Customer::orderBy('name')->get(['name', 'phone', 'email']),
            // For the "asiento no corresponde a este autobús" warning's
            // reassignment dropdown — see SeatReservation::hasSeatMismatch()
            // and TripGuide::linkTrip(). Natural sort so labels read
            // 1,2,3…10,11 instead of the lexicographic 1,10,11,2,20…
            'currentBusSeats' => $landingRoute->busUnit->seats()->bookable()->get()->sortBy('label', SORT_NATURAL)->values(),
            // Any seat already held on THIS trip for a given leg — same
            // scope reassignSeat() itself checks — so the dropdown only
            // ever offers seats that are actually free.
            'takenSeatIdsByLeg' => $landingRoute->seatReservations()
                ->get(['bus_unit_seat_id', 'leg'])
                ->groupBy('leg')
                ->map(fn ($g) => $g->pluck('bus_unit_seat_id')->unique()->values()),
            'standingSeatIds' => $standingSeatIds,
            'standingBySeat' => $standingBySeat,
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
            // way, same as the online-purchase flow tracks it. Keyed by
            // seat_id (payment_method[123]) so a multi-seat apartado can
            // split across methods — e.g. one seat cash, another transfer.
            'paid' => ['required', 'boolean'],
            'payment_method' => ['required', 'array', 'min:1'],
            'payment_method.*' => ['required', 'string', 'in:transfer,card,cash,tbd'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $landingRoute->bus_unit_id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Keyed by seat_id, same shape as payment_method — "ya sé
            // cuándo regresa" per seat (one person in the group might
            // come back a different day than another, or not at all).
            // Meaningless for trip_type=regreso (it already IS a return),
            // the view never shows the field there.
            'return_date' => ['nullable', 'array'],
            'return_date.*' => ['nullable', 'date', 'after_or_equal:tomorrow'],
            // Only meaningful for "ida" (the only case where the return
            // is a brand-new charge) — ignored for redondo/especial,
            // whose return is always free/already paid regardless.
            'return_paid' => ['nullable', 'array'],
            'return_paid.*' => ['nullable', 'boolean'],
            // General — not per seat: when checked, EVERY seat in this
            // apartado also becomes a standing "de planta" assignment for
            // this exact route/bus (see StandingReservation), so every
            // future matching trip auto-books it from now on.
            'mark_as_standing' => ['nullable', 'boolean'],
        ]);

        // Grows the Agenda de clientes automatically — once a name/phone
        // is typed here, it's available to autofill next time instead of
        // retyping it by hand.
        Customer::remember($data['customer_name'], $data['customer_phone'], $data['customer_email'] ?? null);

        $tripType = $data['trip_type'];
        $isPaid = (bool) $data['paid'];
        // Keyed by seat_id — a multi-seat apartado can split across
        // methods (e.g. one seat cash, another transfer).
        $methodsBySeat = $data['payment_method'];
        $distinctMethods = array_values(array_unique($methodsBySeat));
        $isCashPending = ! $isPaid;
        $unitPrice = (float) ($landingRoute->priceFor($tripType)?->price ?? 0);
        $returnDatesBySeat = array_filter($data['return_date'] ?? []);
        $returnPaidBySeat = $data['return_paid'] ?? [];
        // Only meaningful for ida (a brand-new charged sale) and
        // redondo/especial (the already-paid return, just not same day)
        // — "regreso" itself has no return leg of its own to register.
        $allowsReturnDate = in_array($tripType, [
            TripTicketPrice::TYPE_ONE_WAY,
            TripTicketPrice::TYPE_ROUND_TRIP,
            TripTicketPrice::TYPE_ESPECIAL,
        ], true);
        $regresoPrice = $allowsReturnDate
            ? (float) ($landingRoute->priceFor(TripTicketPrice::TYPE_REGRESO)?->price ?? 0)
            : 0.0;

        // Unpaid-by-transfer apartados get the same reference-number
        // workflow as an online transfer purchase, so they can be
        // validated from the exact same /admin/pagos screen. One shared
        // reference covers the whole group even when only some of its
        // seats use transfer — same simplification the group-level
        // transfer-validation flow already makes elsewhere.
        $transferReference = null;
        if ($isCashPending && in_array(SeatReservation::PAYMENT_METHOD_TRANSFER, $distinctMethods, true)) {
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
        //
        // Exception: a seat whose blocking reservation is a round-trip/
        // especial return that's been released for same-day resale
        // (the passenger isn't coming back this day) is NOT "taken" when
        // the NEW apartado is itself a "regreso" — that's exactly the
        // seat this release exists to free up. Any other trip type still
        // sees it as taken (the outbound leg is still real).
        $blockingReservations = $landingRoute->seatReservations()
            ->whereIn('bus_unit_seat_id', $data['seat_ids'])
            ->where(function ($q) {
                $q->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
                    ->orWhereNotNull('user_id');
            })
            ->get();

        $alreadyTaken = $blockingReservations
            ->reject(fn (SeatReservation $r) => $tripType === TripTicketPrice::TYPE_REGRESO && $r->isResaleWindowOpen())
            ->pluck('bus_unit_seat_id')
            ->unique()
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
        try {
            $root = DB::transaction(function () use ($landingRoute, $data, $request, $tripType, $unitPrice, $isCashPending, $methodsBySeat, $transferReference, $blockingReservations, $returnDatesBySeat, $returnPaidBySeat, $allowsReturnDate, $regresoPrice) {
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
                    'payment_method' => $methodsBySeat[$seatId] ?? reset($methodsBySeat),
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

                // "Asiento predeterminado (de planta)" — general checkbox,
                // not per seat: every seat in this apartado also gets a
                // standing assignment for this exact route/bus, so any
                // FUTURE trip auto-books it too (TODAY's seat is already
                // booked above, same as always).
                if (! empty($data['mark_as_standing'])) {
                    StandingReservation::updateOrCreate(
                        [
                            'from' => $landingRoute->from,
                            'to' => $landingRoute->to,
                            'bus_unit_id' => $landingRoute->bus_unit_id,
                            'bus_unit_seat_id' => $seatId,
                        ],
                        [
                            'customer_name' => $data['customer_name'],
                            'customer_phone' => $data['customer_phone'],
                            'customer_email' => $data['customer_email'] ?? null,
                            'trip_type' => $tripType,
                            'notes' => $data['notes'] ?? null,
                            'is_active' => true,
                        ]
                    );
                }

                // "Ya sé cuándo regresa" — registered per seat right here
                // at apartado time instead of waiting for a later
                // Reprogramar regreso. Ida is a brand-new charged sale
                // (the ticket never included a return); redondo/especial
                // is the same free/already-paid reschedule as always,
                // just applied immediately since the admin already knows
                // they won't use the same-day return.
                if ($allowsReturnDate && isset($returnDatesBySeat[$seatId])) {
                    $returnDate = \Illuminate\Support\Carbon::parse($returnDatesBySeat[$seatId])->startOfDay();
                    $isIda = $tripType === TripTicketPrice::TYPE_ONE_WAY;
                    // Independent from the main seat's "paid" toggle —
                    // the admin might apartar the ida as already paid
                    // while the return still needs collecting, or vice
                    // versa. Defaults to not paid when left unset.
                    $returnIsPaid = $isIda && ! empty($returnPaidBySeat[$seatId]);
                    $returnTicket = $new->createReturnLegTicket($returnDate, $isIda ? [
                        'unit_price' => $regresoPrice,
                        'payment_method' => $methodsBySeat[$seatId] ?? reset($methodsBySeat),
                        'payment_status' => $returnIsPaid ? SeatReservation::PAYMENT_COMPLETED : SeatReservation::PAYMENT_PENDING,
                        'paid_at' => $returnIsPaid ? now() : null,
                        'notes' => 'Regreso agendado al apartar el boleto de ida #'.$new->id,
                    ] : []);

                    if (! $isIda) {
                        $new->update([
                            'return_changed_to_reservation_id' => $returnTicket->id,
                            'return_released_at' => now(),
                            'return_released_by' => $request->user()?->id,
                            'return_resale_expires_at' => now()->addHours(Setting::current()->returnResaleValidityHours()),
                        ]);
                    }
                }

                // Claiming a released seat for a "regreso" apartado
                // consumes that release — mark it resold so it can't be
                // claimed twice (same field the online resale flow uses).
                if ($tripType === TripTicketPrice::TYPE_REGRESO) {
                    // Loose match: $seatId comes from the submitted form
                    // (string), bus_unit_seat_id is cast to int by the
                    // model — a strict === here silently never matches.
                    $released = $blockingReservations->first(fn (SeatReservation $r) => (int) $r->bus_unit_seat_id === (int) $seatId && $r->isResaleWindowOpen());
                    $released?->update(['resold_return_reservation_id' => $new->id]);
                }

                $root ??= $new;
            }

            return $root;
            });
        } catch (HttpException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'No se pudo agendar el regreso para uno de los asientos.');
        }

        // Payment is per seat: a transfer seat and a cash seat in the same
        // submission become separate groups so each is confirmed (Pagos)
        // and notified on its own terms. Paid groups get the real ticket,
        // unpaid ones the "reservado — método" notice (deliverGroup()).
        $allSent = true;
        foreach ($root->regroupByPayment() as $groupRoot) {
            $group = $groupRoot->groupMembers()->load(['landingRoute', 'seat']);
            $allSent = $whatsapp->deliverGroup($group, $ticketImages) && $allSent;
        }

        $seatCount = count($data['seat_ids']);
        $tripTypeLabel = TripTicketPrice::tripTypes()[$tripType] ?? $tripType;
        $plural = $seatCount > 1 ? 's' : '';
        $summary = "{$data['customer_name']} ({$seatCount} asiento{$plural}, {$tripTypeLabel}";

        if ($isCashPending) {
            $methodLabel = implode(' y ', array_map(
                fn ($m) => (new SeatReservation(['payment_method' => $m]))->payment_method_label,
                $distinctMethods
            ));
            $activationHint = in_array(SeatReservation::PAYMENT_METHOD_TRANSFER, $distinctMethods, true)
                ? 'Valida la transferencia en Pagos para mandar el boleto con QR.'
                : 'El boleto se imprime/envía cuando confirmes el pago en Pagos.';

            return redirect()
                ->route('admin.asientos.show', $landingRoute)
                ->with($allSent ? 'success' : 'error', $allSent
                    ? "Apartado creado para {$summary}, {$methodLabel}). El cliente recibirá un aviso por WhatsApp. {$activationHint}"
                    : "Apartado creado para {$summary}, {$methodLabel}), pero no se pudo enviar el aviso por WhatsApp automáticamente. {$activationHint}");
        }

        return redirect()
            ->route('admin.asientos.show', $landingRoute)
            ->with($allSent ? 'success' : 'error', $allSent
                ? "Apartado creado y boleto{$plural} enviado{$plural} por WhatsApp a {$summary})."
                : "Apartado creado para {$summary}), pero no se pudo enviar el boleto por WhatsApp automáticamente. Usa el botón \"Enviar boleto\" para reintentar.");
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

        // Whole group, not just the still-"pending" members — a partial
        // resend (after removeSeat(), say) would leave seats out of the ticket.
        $group = $reservation->groupMembers()->load(['landingRoute', 'seat']);

        // A guide that linked to this trip on a different "plantilla" than
        // it was staged for can leave a seat that doesn't actually belong
        // to this bus (see TripGuide::linkTrip()) — never generate/send a
        // boleto with that stale seat until an admin reasigna it.
        if ($group->contains(fn (SeatReservation $r) => $r->hasSeatMismatch())) {
            return back()->with('error', 'Uno o más asientos de este apartado no corresponden al autobús de este viaje — reasígnalos abajo antes de enviar.');
        }

        // Per seat: paid ones get the QR, unpaid ones only the "reservado"
        // notice (never a QR before payment is confirmed).
        if ($whatsapp->deliverGroup($group, $ticketImages)) {
            return back()->with('success', $group->every->isPaymentCompleted()
                ? ($group->count() > 1 ? 'Boletos enviados' : 'Boleto enviado').' por WhatsApp.'
                : 'Aviso de apartado enviado por WhatsApp (el boleto con QR sale al confirmar el pago).');
        }

        return back()->with('error', $whatsapp->isConfigured()
            ? 'No se pudo enviar por WhatsApp — revisa el log.'
            : 'WhatsApp no está configurado — ve a Administración → WhatsApp.');
    }

    /**
     * Manual fix for an apartado a guide linked here on the wrong
     * "plantilla" (see TripGuide::linkTrip()): the seat it's pointing at
     * doesn't belong to this trip's bus unit, so it never got the
     * automatic WhatsApp send. The admin picks a real seat here instead —
     * once fixed, if this group was never sent, re-queue the same
     * notification linkTrip() would have sent originally.
     */
    public function reassignSeat(Request $request, LandingRoute $landingRoute, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->landing_route_id === $landingRoute->id, 404);

        $data = $request->validate([
            'bus_unit_seat_id' => [
                'required',
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $landingRoute->bus_unit_id),
            ],
        ]);

        $taken = SeatReservation::where('landing_route_id', $landingRoute->id)
            ->where('id', '!=', $reservation->id)
            ->where('bus_unit_seat_id', $data['bus_unit_seat_id'])
            ->where('leg', $reservation->leg)
            ->exists();

        if ($taken) {
            return back()->with('error', 'Ese asiento ya está apartado en este viaje.');
        }

        $reservation->update(['bus_unit_seat_id' => $data['bus_unit_seat_id']]);

        // The job itself bails while any OTHER seat in the group is still
        // mismatched, so the dispatch that fires is the last one fixed.
        $group = $reservation->groupMembers();
        if ($group->whereNull('ticket_sent_at')->isNotEmpty()) {
            \App\Jobs\SendLinkedTicketNotification::dispatch($reservation->groupRootId());
        }

        return back()->with('success', 'Asiento reasignado correctamente.');
    }

    /**
     * "No viaja hoy" — the regular "de planta" passenger isn't coming on
     * THIS trip. Cancels the apartado StandingReservation::applyToTrip()
     * auto-booked for them (freeing the seat for a normal apartado just
     * this once) and records the skip so the seat picker stops treating
     * it as standing for this trip specifically — every OTHER matching
     * trip still auto-books it exactly as before.
     */
    public function releaseStanding(LandingRoute $landingRoute, StandingReservation $standing): RedirectResponse
    {
        abort_unless(
            $standing->bus_unit_id === $landingRoute->bus_unit_id
                && $standing->from === $landingRoute->from
                && $standing->to === $landingRoute->to,
            404
        );

        $standing->skips()->firstOrCreate(['landing_route_id' => $landingRoute->id]);

        $landingRoute->seatReservations()
            ->where('bus_unit_seat_id', $standing->bus_unit_seat_id)
            ->where('notes', 'like', 'Asiento de planta%')
            ->delete();

        return back()->with('success', 'Asiento liberado para este viaje — seguirá apartándose solo en los demás.');
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
     * Drop ONE seat out of a multi-seat apartado (the rest stays intact)
     * — for when the customer decides they only need 1 of the 2 they'd
     * reserved, say. Unlike destroy() above, this never touches the
     * other seats. If the removed seat happened to be the group's root
     * (the row carrying transfer_reference/notes/reserved_by), those
     * get handed off to one of the remaining seats first so the group
     * doesn't lose its transfer-matching reference or admin note.
     * Finishes by resending the updated info (ticket or reservation
     * notice, whichever already applied) so the customer sees the
     * correct seat list.
     */
    public function removeSeat(LandingRoute $landingRoute, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if ($landingRoute->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se puede editar el apartado.');
        }

        if ($reservation->isFullyCheckedIn() || $reservation->isOutboundVerified() || $reservation->isReturnVerified()) {
            return back()->with('error', 'No se puede quitar un asiento ya verificado.');
        }

        $group = $reservation->groupMembers();

        if ($group->count() <= 1) {
            return back()->with('error', 'Este apartado solo tiene un asiento — usa "Borrar" para cancelarlo por completo.');
        }

        $remaining = $group->reject(fn (SeatReservation $r) => $r->id === $reservation->id)->values();
        $wasRoot = $reservation->notes === null || ! str_starts_with((string) $reservation->notes, 'group:');

        // Capture what the old root carried before deleting it — its
        // transfer_reference is DB-unique, so it has to be freed up
        // (the row deleted) before the new root can take the same value.
        $oldNotes = $reservation->notes;
        $oldTransferReference = $reservation->transfer_reference;
        $oldTransferExpiresAt = $reservation->transfer_expires_at;
        $oldReservedBy = $reservation->reserved_by;

        $seatLabel = $reservation->seat?->label ?? '—';
        $reservation->delete();

        if ($wasRoot) {
            $newRoot = $remaining->first();
            $newRoot->update([
                'notes' => $oldNotes,
                'transfer_reference' => $oldTransferReference,
                'transfer_expires_at' => $oldTransferExpiresAt,
                'reserved_by' => $oldReservedBy,
            ]);
            SeatReservation::whereIn('id', $remaining->skip(1)->pluck('id'))->update(['notes' => 'group:'.$newRoot->id]);
        }

        $freshGroup = $remaining->first()->fresh()->groupMembers()->load(['landingRoute', 'seat']);

        $resent = $whatsapp->deliverGroup($freshGroup, $ticketImages);

        $message = "Asiento {$seatLabel} quitado del apartado.";

        return back()->with($resent ? 'success' : 'error', $resent
            ? $message.' Se reenvió la información actualizada por WhatsApp.'
            : $message.' No se pudo reenviar la información por WhatsApp automáticamente.');
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

        // Grouped, not keyed — a seat can legitimately carry TWO rows now
        // (an outbound round-trip/especial passenger not returning this
        // day, plus a different "regreso" passenger who claimed the
        // released return leg on that same seat). keyBy() would silently
        // drop one of them.
        $reservationsBySeat = $landingRoute->seatReservations()
            ->where(fn ($q) => $q->whereNull('payment_status')->orWhereNotIn('payment_status', [
                SeatReservation::PAYMENT_FAILED,
                SeatReservation::PAYMENT_REFUNDED,
                SeatReservation::PAYMENT_CHARGEBACK,
            ]))
            ->with(['user'])
            ->get()
            ->groupBy('bus_unit_seat_id');

        // Every bookable seat prints, not just the ones already sold —
        // so the sheet doubles as a boarding checklist showing what's
        // still open, not only who already has a spot.
        $seats = $landingRoute->busUnit->seats()
            ->bookable()
            ->get()
            ->sortBy(fn ($seat) => $seat->label, SORT_NATURAL)
            ->values();

        $pdf = Pdf::loadView('admin.asientos.manifiesto-pdf', [
            'trip' => $landingRoute,
            'seats' => $seats,
            'reservationsBySeat' => $reservationsBySeat,
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
    public function updateCategory(Request $request, LandingRoute $landingRoute, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if ($landingRoute->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se puede editar el apartado.');
        }

        if ($reservation->isFullyCheckedIn()) {
            return back()->with('error', 'No se puede editar un apartado ya verificado.');
        }

        $data = $request->validate([
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            // "card" is preserved as a value for legacy rows but never
            // offered on the form.
            'payment_method' => ['required', 'string', 'in:transfer,cash,card,tbd'],
            'payment_status' => ['required', 'string', 'in:pending,completed'],
        ]);

        $tripType = $data['trip_type'];
        $paid = $data['payment_status'] === SeatReservation::PAYMENT_COMPLETED;
        $before = $reservation->trip_type.'|'.$reservation->payment_method.'|'.$reservation->payment_status;

        // Per ticket: only THIS seat changes — its siblings keep their own
        // category, price and payment.
        $reservation->update([
            'trip_type' => $tripType,
            'leg' => $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND,
            'unit_price' => (float) ($landingRoute->priceFor($tripType)?->price ?? 0),
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            // paid_at keeps its original timestamp once set; unpaying clears
            // it so Pagos sees a pending reservation again.
            'paid_at' => $paid ? ($reservation->paid_at ?? now()) : null,
            // A seat sent as paid and then corrected to pending is no longer
            // a "sent ticket" — only the pending notice applies.
            'status' => $paid ? $reservation->status : SeatReservation::STATUS_PENDING,
        ]);

        // Seats of one apartado can now differ in method/status: keep one
        // group per (method, status) so Pagos and notifications stay right.
        $roots = $reservation->regroupByPayment();

        // The correction must reach the customer too: re-send the group this
        // seat ended up in, if it had been notified before. (Never-notified
        // groups keep waiting for the manual "Enviar" button.)
        $changed = $before !== $reservation->trip_type.'|'.$reservation->payment_method.'|'.$reservation->payment_status;
        $resent = null;
        if ($changed) {
            $members = $reservation->fresh()->groupMembers()->load(['landingRoute', 'seat']);
            if ($members->whereNotNull('ticket_sent_at')->isNotEmpty()) {
                $resent = $whatsapp->deliverGroup($members, $ticketImages);
            }
        }

        $summary = 'Asiento '.($reservation->seat?->label ?? '—').' → '
            .(TripTicketPrice::tripTypes()[$tripType] ?? $tripType).' · '
            .$reservation->fresh()->payment_method_label.' · '.($paid ? 'pagado' : 'pendiente');

        $notice = match ($resent) {
            true => ' Se reenvió la información actualizada por WhatsApp.',
            false => ' No se pudo reenviar por WhatsApp — usa "Enviar boleto".',
            default => '',
        };

        return back()->with($resent === false ? 'error' : 'success', 'Boleto actualizado. '.$summary.'.'.$notice);
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
