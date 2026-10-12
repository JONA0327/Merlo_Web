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
use App\Services\EvolutionWhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function index(Request $request): View
    {
        // Keeps every recurring trip's guide rolling ~4 weeks ahead just
        // by visiting this page — see TripGuide::ensureForRecurringTrip()
        // (also run daily by the guides:provision-recurring command, so
        // the window stays fresh even if nobody opens this screen).
        $recurringTrips = LandingRoute::where('is_active', true)->whereNotNull('recurring_weekdays')->get();
        foreach ($recurringTrips as $trip) {
            TripGuide::ensureForRecurringTrip($trip);
        }

        // Every "de planta" assignment gets a real apartado staged for
        // each upcoming day it applies to within its guide's window —
        // not just a purple color on the seat picker (see
        // provisionGuideReservations()). Runs for every guide, not only
        // recurring-trip ones — a manually created guide's own standing
        // assignments work the same way.
        foreach (TripGuide::all() as $guide) {
            StandingReservation::provisionGuideReservations($guide);
        }

        $guides = TripGuide::query()
            ->withCount(['pendingSeatReservations as pending_count'])
            ->with('busUnit')
            ->orderByDesc('date_to')
            ->get();

        // Sunday-first week, matching recurring_weekdays' convention
        // (Carbon's own dayOfWeek: 0=Dom…6=Sáb). ?inicio= moves the
        // window back/forward a week at a time (see the blade view).
        $weekStart = Carbon::parse($request->query('inicio', now()->toDateString()))->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        // Per-day pending counts for the badges in the week tabs — a
        // guide's own pending_count (above) spans its whole 4-week
        // window, so showing that same number on every day's tab made
        // every tab claim the guide's ENTIRE backlog, not just what's
        // actually reserved for that one date. Keyed "guideId|Y-m-d".
        $dayCounts = SeatReservation::whereNull('landing_route_id')
            ->whereIn('trip_guide_id', $guides->pluck('id'))
            ->whereBetween('travel_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get(['trip_guide_id', 'travel_date'])
            ->countBy(fn (SeatReservation $r) => $r->trip_guide_id.'|'.$r->travel_date->toDateString());

        // Every guide that belongs to SOME recurring trip (by from/to/
        // bus_unit_id), computed once — not per day. These must NEVER
        // fall through to the "manual guide" bucket below, on ANY day,
        // even one their own weekday pattern doesn't cover: a guide's
        // date_from/date_to is a continuous 4-week span (so coversDate()
        // is true for every day in it, recurring pattern or not) —
        // checking "did this guide match TODAY's recurring filter" was
        // the bug: on a day the trip doesn't run, that's false, so the
        // guide wrongly looked "manual" and got shown anyway.
        $recurringGuideIds = collect($recurringTrips->all())
            ->map(fn (LandingRoute $trip) => $guides->first(fn (TripGuide $g) => $g->from === $trip->from && $g->to === $trip->to && $g->bus_unit_id === $trip->bus_unit_id))
            ->filter()
            ->pluck('id');

        $days = collect(range(0, 6))->map(function (int $i) use ($weekStart, $recurringTrips, $guides, $recurringGuideIds, $dayCounts) {
            $date = $weekStart->copy()->addDays($i);

            // EVERY recurring trip shows up every day — not just the ones
            // that run today — so the admin always sees the full route
            // list and which ones are "bloqueado" today, not just a
            // silently shorter list. 'active' says whether this exact
            // weekday is in the trip's own pattern; the blade renders
            // inactive ones grayed out and unclickable instead of a link.
            // collect(...->all()) up front: mapping an Eloquent Collection
            // to plain arrays still leaves it typed as an Eloquent
            // Collection (map() does `new static(...)`), and its merge()
            // override assumes model items (calls ->getKey() on them) —
            // crashes once the items are arrays. Plain Collection::merge()
            // doesn't have that assumption.
            $recurringTabs = collect($recurringTrips->all())
                ->map(function (LandingRoute $trip) use ($guides, $date, $dayCounts) {
                    $guide = $guides->first(fn (TripGuide $g) => $g->from === $trip->from && $g->to === $trip->to && $g->bus_unit_id === $trip->bus_unit_id);

                    return $guide ? [
                        'label' => "{$trip->from} → {$trip->to}",
                        'guide' => $guide,
                        'color' => $trip->color,
                        'active' => in_array($date->dayOfWeek, $trip->recurring_weekdays ?? [], true),
                        'pending_count' => $dayCounts->get($guide->id.'|'.$date->toDateString(), 0),
                    ] : null;
                })
                ->filter()
                ->values();

            // Manually-created guides (non-recurring routes) whose range
            // covers this date — a recurring trip's own guide is excluded
            // unconditionally (see $recurringGuideIds above). Manual
            // guides have no weekday pattern to "block" against — any day
            // inside their range is simply valid, so they're always active.
            $manualTabs = collect($guides->all())
                ->filter(fn (TripGuide $g) => ! $recurringGuideIds->contains($g->id) && $g->coversDate($date))
                ->map(fn (TripGuide $g) => [
                    'label' => "{$g->from} → {$g->to}",
                    'guide' => $g,
                    'active' => true,
                    'pending_count' => $dayCounts->get($g->id.'|'.$date->toDateString(), 0),
                ])
                ->values();

            // Active tabs first, blocked ones trail behind so the usable
            // links aren't buried under grayed-out routes.
            return [
                'date' => $date,
                // Same cutoff show() itself already uses for its own
                // "Fecha pasada — solo lectura" notice — today is still
                // open, only strictly-before-today closes. A closed day
                // stays a real, clickable link (apartados already made
                // that day are still there to consult) — show() already
                // blocks the apartar form and leaves the pending list
                // visible for a past date, this just surfaces that same
                // state up in the week view instead of only after clicking in.
                'isPast' => $date->lt(Carbon::today()),
                'tabs' => $recurringTabs->merge($manualTabs)->where('active', true)->values(),
            ];
        });

        return view('admin.guias.index', [
            'guides' => $guides,
            'days' => $days,
            'weekStart' => $weekStart,
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

        // Apartados list filters (nombre, pagado, categoría, método) —
        // applied to the roots only, so a matching apartado still shows
        // all of its seats. The full $allPending set stays untouched for
        // the seat map and the reassignment dropdowns below.
        $filters = $request->only(['q', 'paid', 'trip_type', 'payment_method']);
        $hasActiveFilters = (bool) array_filter($filters, fn ($v) => filled($v));
        if ($hasActiveFilters) {
            $matchingIds = $guide->pendingSeatReservations()->listFilters($filters)->pluck('id')->flip();
            $roots = $roots->filter(fn (SeatReservation $r) => $matchingIds->has($r->id));
        } else {
            // No active search — only the currently-selected "Fecha a
            // apartar" shows here, not the whole guide. A standing
            // assignment now stages a real apartado for every matching
            // day across the whole guide window (see
            // StandingReservation::provisionGuideReservations()), which
            // used to flood this list with weeks of entries; the week
            // view's per-day badges already surface what's further out,
            // and the seat map above already shows this date's own
            // apartados in context. An explicit search lifts the date
            // scope, since the admin is looking for something specific
            // regardless of date.
            $roots = $roots->filter(fn (SeatReservation $r) => $r->travel_date->isSameDay($date));
        }
        $groupSeatsByNote = $allPending->filter(fn (SeatReservation $r) => str_starts_with((string) $r->notes, 'group:'))->groupBy('notes');

        // A standing-sourced apartado (marked "Asiento predeterminado" at
        // apartar time, see storeReservation()) lists as its own separate
        // card per seat here — same as a future auto-provisioned one —
        // instead of the one bundled card a normal multi-seat sale gets.
        // Payment/WhatsApp grouping (notes="group:<id>", regroupByPayment())
        // is untouched; this only reshapes what this list shows.
        $roots = $roots->flatMap(function (SeatReservation $r) use ($groupSeatsByNote) {
            $members = $groupSeatsByNote->get('group:'.$r->id, collect());

            if (! str_starts_with((string) $r->notes, 'Asiento de planta')) {
                $r->setRelation('groupSeats', $members);

                return [$r];
            }

            // Every split-off card shows the "Asiento de planta" label,
            // not just the one that happened to carry it in the
            // database — the other seats' actual notes ("group:<id>")
            // stay in the database untouched (regroupByPayment() still
            // needs it); this only overrides the in-memory copy used to
            // render this list.
            return $members->push($r)->values()->each(
                fn (SeatReservation $seat) => $seat->setRelation('groupSeats', collect())->setAttribute('notes', $r->notes)
            );
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
        $standingSeatIds = StandingReservation::activeSeatIdsFor($guide->from, $guide->to, $guide->bus_unit_id, null, $date->dayOfWeek);

        return view('admin.guias.show', [
            'guide' => $guide,
            'date' => $date,
            'pendingReservationsForDate' => $pendingForDate->keyBy('bus_unit_seat_id'),
            'rootsByCustomer' => $rootsByCustomer,
            'filters' => $filters,
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

    public function storeReservation(Request $request, TripGuide $guide, EvolutionWhatsAppService $whatsapp): RedirectResponse
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
            // Abono: partial payment for the whole apartado (stored on the root seat only).
            'abono' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
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
        // borrow whatever this exact route/bus is already charging on
        // another trip, if any (see estimateUnitPrice()); otherwise 0,
        // same as an unconfigured trip shows "sin precio" elsewhere.
        $regresoPrice = $allowsReturnDate
            ? TripTicketPrice::estimateUnitPrice($guide->from, $guide->to, $guide->bus_unit_id, TripTicketPrice::TYPE_REGRESO)
            : 0.0;
        // Same reasoning as $regresoPrice above — needed so the immediate
        // WhatsApp confirmation below can show an actual total instead of
        // $0 while this apartado waits on a real trip to price off of.
        $estimatedUnitPrice = TripTicketPrice::estimateUnitPrice($guide->from, $guide->to, $guide->bus_unit_id, $data['trip_type'], count($data['seat_ids']));

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
            $root = DB::transaction(function () use ($guide, $data, $request, $tripType, $date, $isCashPending, $methodsBySeat, $returnDatesBySeat, $returnPaidBySeat, $allowsReturnDate, $regresoPrice, $estimatedUnitPrice) {
                $root = null;
                    $abonoLeft = (float) ($data['abono'] ?? 0);
                foreach ($data['seat_ids'] as $seatId) {
                    // Abono: covers seats in order as far as it reaches; a seat it covers
                    // fully is paid, the first one it can't is pending but keeps what was abonado.
                    $seatPrice = (float) $estimatedUnitPrice;
                    $seatAbono = min($abonoLeft, $seatPrice);
                    $abonoLeft -= $seatAbono;
                    $seatCovered = $isCashPending && $seatPrice > 0 && $seatAbono >= $seatPrice;
                    $seatPending = $isCashPending && ! $seatCovered;
                    $new = SeatReservation::create([
                        'trip_guide_id' => $guide->id,
                        'travel_date' => $date->toDateString(),
                        'bus_unit_seat_id' => $seatId,
                        'trip_type' => $tripType,
                        'leg' => $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND,
                        // Estimated off the global default price — the
                        // real one isn't known until the trip itself
                        // exists; TripGuide::linkTrip() recomputes it for
                        // real at that point (see $estimatedUnitPrice above).
                        'unit_price' => $estimatedUnitPrice,
                        'abono' => $seatAbono,
                        'customer_name' => $data['customer_name'],
                        'customer_email' => $data['customer_email'] ?? null,
                        'customer_phone' => $data['customer_phone'],
                        'status' => SeatReservation::STATUS_PENDING,
                        'payment_method' => $methodsBySeat[$seatId] ?? reset($methodsBySeat),
                        'payment_status' => $seatPending ? SeatReservation::PAYMENT_PENDING : SeatReservation::PAYMENT_COMPLETED,
                        'paid_at' => $seatPending ? null : now(),
                        'reserved_by' => $request->user()?->id,
                        // Payment/WhatsApp still treat this apartado as
                        // one group (group:<root id>, same as any other
                        // multi-seat sale — see $root below and
                        // regroupByPayment()) regardless of mark_as_
                        // standing; the root's own notes just carries the
                        // "Asiento de planta" marker instead of the free-
                        // text notes field when it's checked, so show()
                        // can split it back into one card per seat for
                        // display, same as a future auto-provisioned one
                        // (see StandingReservation::provisionGuideReservations()).
                        'notes' => $root
                            ? 'group:'.$root->id
                            : (! empty($data['mark_as_standing'])
                                ? 'Asiento de planta'.(! empty($data['notes']) ? ' — '.$data['notes'] : '')
                                : ($data['notes'] ?? null)),
                    ]);

                    if (! empty($data['mark_as_standing'])) {
                        StandingReservation::markStanding(
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
                            ],
                            $date->dayOfWeek
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
            $roots = $root->regroupByPayment();

            // mark_as_standing isn't per seat — a single pass covers
            // every seat just marked, staging this guide's upcoming
            // matching days right away instead of waiting for the next
            // /admin/guias visit or the daily cron.
            if (! empty($data['mark_as_standing'])) {
                StandingReservation::provisionGuideReservations($guide);
            }
        } catch (HttpException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'No se pudo agendar el regreso para uno de los asientos.');
        }

        // Immediate "quedó reservado" confirmation — every Guía apartado
        // gets this the moment it's created, regardless of payment status
        // (unlike a real trip's apartado, which already sends something
        // right away via Apartar asientos — a Guía one otherwise waits in
        // silence until the real trip opens and SendLinkedTicketNotification
        // finally fires, which can be weeks later). One per resulting
        // group — regroupByPayment() above may have split the original
        // selection into several if it mixed payment methods/status.
        foreach ($roots as $groupRoot) {
            try {
                $whatsapp->sendReservationNotice($groupRoot->groupMembers());
            } catch (\Throwable $e) {
                Log::warning('Guía reservation-confirmation notice failed for reservation '.$groupRoot->id.': '.$e->getMessage());
            }
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
