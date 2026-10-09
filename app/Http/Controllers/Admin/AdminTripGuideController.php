<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\Destination;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\StandingReservation;
use App\Models\TripGuide;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Guías": seats reserved for a route/date range BEFORE the real trip
 * exists in the system. Some trips aren't created here until the same
 * day or a few days before departure, but customers call ahead to
 * reserve specific seats — a guide stages those reservations against a
 * bus unit "template" and a chosen date; TripGuide::linkTrip() attaches
 * them automatically once a matching LandingRoute is created.
 */
class AdminTripGuideController extends Controller
{
    public function index(): View
    {
        $guides = TripGuide::query()
            ->withCount(['pendingSeatReservations as pending_count'])
            ->with('busUnit')
            ->orderByDesc('date_to')
            ->get();

        return view('admin.guias.index', [
            'guides' => $guides,
        ]);
    }

    public function create(): View
    {
        return view('admin.guias.create', [
            'busUnits' => BusUnit::where('is_active', true)->orderBy('name')->get(),
            'destinations' => Destination::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'bus_unit_id' => ['required', 'exists:bus_units,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $guide = TripGuide::create($data);

        return redirect()->route('admin.guias.show', $guide)->with('success', 'Guía creada. Ya puedes apartar asientos por fecha.');
    }

    public function show(Request $request, TripGuide $guide): View|RedirectResponse
    {
        $guide->load('busUnit.seats');

        $date = $this->resolveDate($request, $guide);

        // If a real trip already matching this route/bus/date exists AND
        // nothing is left pending for it (see isDateResolved() — pending
        // RETURN legs don't count as "resolved", they're waiting on a
        // trip going the OPPOSITE direction that this never checks), the
        // guide has already done its job for that day — send the admin
        // to the normal Apartar screen instead of letting two parallel
        // booking paths fight over the same seats.
        if ($this->isDateResolved($guide, $date)) {
            return redirect()
                ->route('admin.asientos.show', $this->matchingTrip($guide, $date))
                ->with('success', 'Ya existe un viaje abierto para esta fecha — los asientos se apartan ahí directamente.');
        }

        $pendingForDate = $guide->pendingSeatReservations()
            ->whereDate('travel_date', $date->toDateString())
            ->get(['id', 'bus_unit_seat_id', 'status', 'source_reservation_id']);

        $allPending = $guide->pendingSeatReservations()
            ->with('seat')
            ->orderBy('travel_date')
            ->orderBy('created_at')
            ->get();

        $roots = $allPending->filter(fn (SeatReservation $r) => $r->notes === null || ! str_starts_with((string) $r->notes, 'group:'));
        $groupSeatsByNote = $allPending->filter(fn (SeatReservation $r) => str_starts_with((string) $r->notes, 'group:'))->groupBy('notes');
        $roots->each(function (SeatReservation $r) use ($groupSeatsByNote) {
            $r->setRelation('groupSeats', $groupSeatsByNote->get('group:'.$r->id, collect()));
        });

        // Grouped by customer so the same passenger's several boletos
        // (e.g. outbound + a later "regreso" reventa) list together under
        // one name instead of repeating it in scattered cards.
        $rootsByCustomer = $roots->sortBy('travel_date')
            ->groupBy('customer_name')
            ->sortKeys();

        // Seats with an active "de planta" assignment for this exact
        // route/bus — painted purple and blocked from manual selection on
        // the picker, same as Apartar asientos (StandingReservation
        // never auto-applies to a guide, only a real trip — see
        // applyToTrip() — so there's no per-guide skip concept here).
        $standingSeatIds = StandingReservation::activeSeatIdsFor($guide->from, $guide->to, $guide->bus_unit_id);

        return view('admin.guias.show', [
            'guide' => $guide,
            'date' => $date,
            'pendingReservationsForDate' => $pendingForDate->keyBy('bus_unit_seat_id'),
            'rootsByCustomer' => $rootsByCustomer,
            // For the "asiento no disponible en este autobús" warning's
            // reassignment dropdown — a plantilla switch (see
            // remapSeatsTo()) can leave an apartado pointing at a seat
            // that no longer belongs to this guide's current bus unit.
            // Natural sort so labels read 1,2,3…10,11 instead of the
            // lexicographic 1,10,11,2,20…
            'currentBusSeats' => $guide->busUnit->seats()->bookable()->get()->sortBy('label', SORT_NATURAL)->values(),
            // Seats already held in THIS guide for a given date+leg — same
            // scope reassignSeat() itself checks — so the dropdown only
            // ever offers seats that are actually free that day.
            'takenSeatIdsByDateLeg' => $allPending->groupBy(fn (SeatReservation $r) => $r->travel_date->toDateString().'|'.$r->leg)
                ->map(fn ($g) => $g->pluck('bus_unit_seat_id')->unique()->values()),
            'standingSeatIds' => $standingSeatIds,
        ]);
    }

    public function storeReservation(Request $request, TripGuide $guide): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            'travel_date' => ['required', 'date', 'after_or_equal:'.$guide->date_from->toDateString(), 'before_or_equal:'.$guide->date_to->toDateString()],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $guide->bus_unit_id),
            ],
            // Same "paid independent from payment method, keyed by seat"
            // shape as Apartar asientos — a group can split across
            // methods, and whether it's paid decides whether the real
            // ticket ships (vs. just a reservation notice) the moment
            // this links to a real trip; see SendLinkedTicketNotification.
            'paid' => ['required', 'boolean'],
            'payment_method' => ['required', 'array', 'min:1'],
            'payment_method.*' => ['required', 'string', 'in:transfer,card,cash,tbd'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Same "ya sé cuándo regresa, por asiento" field as Apartar
            // asientos (AdminSeatReservationController::store()) — the
            // guide has no real trip/landing_route_id yet, so this just
            // stages the return leg too (see SeatReservation::
            // createReturnLegTicket()'s guide-staging branch).
            'return_date' => ['nullable', 'array'],
            'return_date.*' => ['nullable', 'date', 'after_or_equal:tomorrow'],
            'return_paid' => ['nullable', 'array'],
            'return_paid.*' => ['nullable', 'boolean'],
            // General — not per seat: same as Apartar asientos, marks
            // every seat in this apartado as a standing "de planta"
            // assignment for this route/bus (see StandingReservation).
            'mark_as_standing' => ['nullable', 'boolean'],
        ]);

        $date = Carbon::parse($data['travel_date'])->startOfDay();
        $isCashPending = ! (bool) $data['paid'];
        $methodsBySeat = $data['payment_method'];
        $returnDatesBySeat = array_filter($data['return_date'] ?? []);
        $returnPaidBySeat = $data['return_paid'] ?? [];
        // Only meaningful for ida (brand-new charge) and redondo/especial
        // (free/already-paid return, just not same day) — same rule as
        // Apartar asientos.
        $allowsReturnDate = in_array($data['trip_type'], [
            TripTicketPrice::TYPE_ONE_WAY,
            TripTicketPrice::TYPE_ROUND_TRIP,
            TripTicketPrice::TYPE_ESPECIAL,
        ], true);
        // No real trip exists yet to price the "regreso" leg off of —
        // fall back to the global default (same one a new trip gets
        // seeded with), good enough until the real trip opens and an
        // admin can still correct it from /admin/precios or /admin/pagos.
        $regresoPrice = $allowsReturnDate
            ? (float) (Setting::current()->defaultPrices()[TripTicketPrice::TYPE_REGRESO] ?? 0)
            : 0.0;

        if ($this->matchingTrip($guide, $date)) {
            return back()->withInput()->with('error', 'Ya existe un viaje abierto para esta fecha — usa Apartar asientos directamente ahí.');
        }

        $tripType = $data['trip_type'];
        $leg = $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND;

        // A physical seat can't be double-booked for the same LEG on the
        // same date, regardless of which guide staged it. But an
        // outbound (ida/redondo/especial) booking and a "regreso" one
        // DON'T conflict on the same seat+date — they're different
        // people on different legs of the same "escala" (the round-trip
        // passenger going out that day, someone else coming back that
        // day on the reverse run) — same model the real-trip seat picker
        // already uses for a released return.
        $alreadyTaken = SeatReservation::whereNull('landing_route_id')
            ->whereNotNull('trip_guide_id')
            ->whereIn('bus_unit_seat_id', $data['seat_ids'])
            ->whereDate('travel_date', $date->toDateString())
            ->where('leg', $leg)
            ->pluck('bus_unit_seat_id')
            ->all();

        if (! empty($alreadyTaken)) {
            $labels = BusUnitSeat::whereIn('id', $alreadyTaken)->pluck('label')->all();

            return back()->withInput()->with('error', 'Los siguientes asientos ya están apartados para esa fecha: '.implode(', ', $labels));
        }

        // "one_way" and "especial" can use any bookable seat — no
        // allowed_trip_type/zone restriction applies to them.
        $mismatched = $guide->busUnit->seats()
            ->whereIn('id', $data['seat_ids'])
            ->get()
            ->filter(fn ($seat) => ! in_array($tripType, [TripTicketPrice::TYPE_ONE_WAY, TripTicketPrice::TYPE_ESPECIAL], true)
                && ! $seat->allowsTripType($tripType))
            ->pluck('label')
            ->all();

        if (! empty($mismatched)) {
            return back()->withInput()->with('error', 'Estos asientos no están disponibles para el tipo de viaje seleccionado: '.implode(', ', $mismatched));
        }

        try {
            $root = DB::transaction(function () use ($guide, $data, $request, $tripType, $date, $isCashPending, $methodsBySeat, $returnDatesBySeat, $returnPaidBySeat, $allowsReturnDate, $regresoPrice) {
                $root = null;
                foreach ($data['seat_ids'] as $seatId) {
                    $new = SeatReservation::create([
                        'trip_guide_id' => $guide->id,
                        'travel_date' => $date->toDateString(),
                        'bus_unit_seat_id' => $seatId,
                        'trip_type' => $tripType,
                        'leg' => $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND,
                        // The real price isn't known until the trip itself
                        // exists — TripGuide::linkTrip() fills this in.
                        'unit_price' => 0,
                        'customer_name' => $data['customer_name'],
                        'customer_email' => $data['customer_email'] ?? null,
                        'customer_phone' => $data['customer_phone'],
                        'status' => SeatReservation::STATUS_PENDING,
                        'payment_method' => $methodsBySeat[$seatId] ?? reset($methodsBySeat),
                        'payment_status' => $isCashPending ? SeatReservation::PAYMENT_PENDING : SeatReservation::PAYMENT_COMPLETED,
                        'paid_at' => $isCashPending ? null : now(),
                        'reserved_by' => $request->user()?->id,
                        'notes' => $root ? 'group:'.$root->id : ($data['notes'] ?? null),
                    ]);

                    if (! empty($data['mark_as_standing'])) {
                        StandingReservation::updateOrCreate(
                            [
                                'from' => $guide->from,
                                'to' => $guide->to,
                                'bus_unit_id' => $guide->bus_unit_id,
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

                    // "Ya sé cuándo regresa" — registered per seat right
                    // here, same as Apartar asientos (see
                    // AdminSeatReservationController::store()).
                    if ($allowsReturnDate && isset($returnDatesBySeat[$seatId])) {
                        $returnDate = Carbon::parse($returnDatesBySeat[$seatId])->startOfDay();
                        $isIda = $tripType === TripTicketPrice::TYPE_ONE_WAY;
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

                    $root ??= $new;
                }

                return $root;
            });

            // Payment is per seat: one group per (method, status) so the
            // notice sent when the trip opens is right for each.
            $root->regroupByPayment();
        } catch (HttpException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'No se pudo agendar el regreso para uno de los asientos.');
        }

        $seatCount = count($data['seat_ids']);

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()])
            ->with('success', "Apartado en guía creado para {$data['customer_name']} ({$seatCount} asiento".($seatCount === 1 ? '' : 's').", {$date->format('d/m/Y')}). Se vinculará al viaje real en cuanto se abra.");
    }

    public function destroyReservation(TripGuide $guide, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->trip_guide_id === $guide->id, 404);

        if (! $reservation->isGuidePending()) {
            return back()->with('error', 'Este apartado ya se vinculó a un viaje real — cancélalo desde Apartar asientos.');
        }

        $date = $reservation->travel_date;
        $group = $reservation->groupMembers();
        SeatReservation::whereIn('id', $group->pluck('id'))->delete();

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $date?->toDateString()])
            ->with('success', $group->count() > 1
                ? 'Apartado de guía cancelado ('.$group->count().' asientos).'
                : 'Apartado de guía cancelado.');
    }

    /**
     * Manual fix for an apartado left "huérfano" by update() switching
     * the guide's bus unit (see remapSeatsTo()): when the old seat's
     * label doesn't exist on the new unit, the reservation keeps pointing
     * at a seat that no longer belongs to this guide's bus — the admin
     * picks a real seat on the CURRENT bus unit here instead.
     */
    public function reassignSeat(Request $request, TripGuide $guide, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->trip_guide_id === $guide->id, 404);

        $data = $request->validate([
            'bus_unit_seat_id' => [
                'required',
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $guide->bus_unit_id),
            ],
        ]);

        // Same leg-aware collision rule as storeReservation(): a seat can't
        // be double-booked for the same leg/date, but an outbound and a
        // "regreso" can share one.
        $taken = SeatReservation::whereNull('landing_route_id')
            ->whereNotNull('trip_guide_id')
            ->where('trip_guide_id', $guide->id)
            ->where('id', '!=', $reservation->id)
            ->where('bus_unit_seat_id', $data['bus_unit_seat_id'])
            ->whereDate('travel_date', $reservation->travel_date?->toDateString())
            ->where('leg', $reservation->leg)
            ->exists();

        if ($taken) {
            return back()->with('error', 'Ese asiento ya está apartado para esa fecha.');
        }

        $reservation->update(['bus_unit_seat_id' => $data['bus_unit_seat_id']]);

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $reservation->travel_date?->toDateString()])
            ->with('success', 'Asiento reasignado correctamente.');
    }

    public function edit(TripGuide $guide): View
    {
        return view('admin.guias.edit', [
            'guide' => $guide,
            'busUnits' => BusUnit::where('is_active', true)->orderBy('name')->get(),
            'destinations' => Destination::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, TripGuide $guide): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'bus_unit_id' => ['required', 'exists:bus_units,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $warning = null;

        // Changing the "plantilla" (bus unit) mid-guide: the already
        // staged reservations point at seats on the OLD bus unit, so
        // before the switch, try to re-point each one to the
        // same-labeled seat on the new unit. Whatever doesn't have a
        // matching label can't follow automatically — warn so the admin
        // goes fix those manually instead of silently losing track of
        // them.
        if ((int) $data['bus_unit_id'] !== $guide->bus_unit_id) {
            $newBusUnit = BusUnit::findOrFail($data['bus_unit_id']);
            $unmatched = $guide->remapSeatsTo($newBusUnit);

            if ($unmatched->isNotEmpty()) {
                $warning = 'No se pudieron ligar al mismo asiento en el otro autobús: '.$unmatched->implode(', ').'. Revisa esos apartados manualmente.';
            }

            // "De planta" belongs to the route, not to whichever bus is
            // running it right now — carry those over too (see
            // StandingReservation::remapToBusUnit()).
            $unmatchedStanding = StandingReservation::remapToBusUnit($guide->from, $guide->to, $guide->bus_unit_id, $newBusUnit);

            if ($unmatchedStanding->isNotEmpty()) {
                $standingWarning = 'Asientos de planta que no se pudieron pasar al nuevo autobús: '.$unmatchedStanding->implode(', ').'. Revísalos en /admin/planta.';
                $warning = $warning ? $warning.' '.$standingWarning : $standingWarning;
            }
        }

        $guide->update($data);

        $redirect = redirect()->route('admin.guias.show', $guide)->with('success', 'Guía actualizada.');

        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function destroy(TripGuide $guide): RedirectResponse
    {
        SeatReservation::whereIn('id', $guide->pendingSeatReservations()->pluck('id'))->delete();
        $guide->delete();

        return redirect()->route('admin.guias.index')->with('success', 'Guía eliminada.');
    }

    private function resolveDate(Request $request, TripGuide $guide): Carbon
    {
        $requested = $request->query('fecha');

        if ($requested) {
            try {
                $date = Carbon::parse($requested)->startOfDay();
                if ($guide->coversDate($date)) {
                    return $date;
                }
            } catch (\Throwable) {
                // fall through to the default below
            }
        }

        // Default to the first date in range that ISN'T already resolved
        // (a real trip open for it with nothing left pending) — otherwise
        // the admin lands straight on the "ya existe un viaje" redirect
        // for whichever date happens to be date_from, with no way to
        // reach the OTHER dates still waiting in this same guide (the
        // date picker on the page never even renders before the redirect
        // fires).
        $cursor = $guide->date_from->copy()->startOfDay();
        $end = $guide->date_to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if (! $this->isDateResolved($guide, $cursor)) {
                return $cursor->copy();
            }
            $cursor->addDay();
        }

        return $guide->date_from->copy()->startOfDay();
    }

    private function matchingTrip(TripGuide $guide, Carbon $date): ?LandingRoute
    {
        return LandingRoute::query()
            ->where('from', $guide->from)
            ->where('to', $guide->to)
            ->where('bus_unit_id', $guide->bus_unit_id)
            ->whereDate('day', $date->toDateString())
            ->first();
    }

    /**
     * A date is "done" for this guide once a real trip exists for it AND
     * there's nothing still pending — specifically, no pending RETURN
     * legs (source_reservation_id set), which wait on a trip going the
     * opposite direction and are unaffected by this guide's own
     * direction having a trip.
     */
    private function isDateResolved(TripGuide $guide, Carbon $date): bool
    {
        if (! $this->matchingTrip($guide, $date)) {
            return false;
        }

        return ! $guide->pendingSeatReservations()
            ->whereNotNull('source_reservation_id')
            ->whereDate('travel_date', $date->toDateString())
            ->exists();
    }
}
