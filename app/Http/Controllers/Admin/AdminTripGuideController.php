<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\StandingReservation;
use App\Models\TripCorte;
use App\Models\TripGuide;
use App\Models\TripTicketPrice;
use App\Services\EvolutionWhatsAppService;
use App\Services\TicketImageService;
use App\Services\TripCorteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        // Best-effort: this is opportunistic sync on a GET, not something
        // the page itself needs to render — a failure here (e.g. a
        // read-only DB connection, a transient DB hiccup) shouldn't turn
        // a simple page view into a 500; the daily commands still run it.
        try {
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
        } catch (\Throwable $e) {
            Log::warning('Guías index: opportunistic sync failed — '.$e->getMessage());
            $recurringTrips ??= LandingRoute::where('is_active', true)->whereNotNull('recurring_weekdays')->get();
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

    /**
     * Every trip that's already come and gone — soft-deleted by
     * trips:prune-expired, so it drops off index() and the weekly view,
     * but still consultable here (payment history, resending a ticket,
     * reprinting the manifest). Ported from
     * AdminSeatReservationController::historico(); each row is a real
     * LandingRoute instance, matched back to its guide via matchesTrip()
     * so the action links can go through admin.guias.show/manifest.
     */
    public function historico(): View
    {
        $routes = LandingRoute::onlyTrashed()
            ->withCount([
                'seatReservations as pending_reservations_count' => fn ($q) => $q
                    ->where('status', SeatReservation::STATUS_PENDING)
                    ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email')),
                'seatReservations as sent_reservations_count' => fn ($q) => $q
                    ->where('status', SeatReservation::STATUS_SENT)
                    ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email')),
            ])
            ->whereNull('recurring_weekdays')
            ->get()
            ->sortByDesc(fn (LandingRoute $r) => $r->return_date ?? $r->day);

        $guides = TripGuide::all();
        $routes->each(function (LandingRoute $route) use ($guides) {
            $route->setAttribute('guide', $guides->first(fn (TripGuide $g) => $g->matchesTrip($route)));
        });

        return view('admin.guias.historico', ['routes' => $routes]);
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

    public function show(Request $request, TripGuide $guide): View
    {
        $guide->load('busUnit.seats');

        $date = $this->resolveDate($request, $guide);

        // Once a real trip exists for this date, THIS is where seats
        // actually get sold/managed — no more bouncing to a separate
        // "Apartar asientos" screen (the old redirect here was the exact
        // source of "en guía aparecen otros pero al activarse ya no
        // aparecen"). Renders the real-trip screen directly, same URL.
        if ($openTrip = $this->matchingTrip($guide, $date)) {
            return $this->showOpenTrip($guide, $openTrip);
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
        $dateUnit = $guide->busUnitFor($date);
        $standingSeatIds = StandingReservation::activeSeatIdsFor($guide->from, $guide->to, $dateUnit->id, null, $date->dayOfWeek);

        return view('admin.guias.show', [
            'guide' => $guide,
            'date' => $date,
            'dateUnit' => $dateUnit,
            'busUnits' => BusUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'pendingReservationsForDate' => $pendingForDate->keyBy('bus_unit_seat_id'),
            'rootsByCustomer' => $rootsByCustomer,
            'filters' => $filters,
            // For the "asiento no disponible en este autobús" warning's
            // reassignment dropdown — a plantilla switch (see
            // remapSeatsTo()) can leave an apartado pointing at a seat
            // that no longer belongs to this guide's current bus unit.
            // Natural sort so labels read 1,2,3…10,11 instead of the
            // lexicographic 1,10,11,2,20…
            'currentBusSeats' => $dateUnit->seats()->bookable()->get()->sortBy('label', SORT_NATURAL)->values(),
            // Seats already held in THIS guide for a given date+leg — same
            // scope reassignSeat() itself checks — so the dropdown only
            // ever offers seats that are actually free that day.
            'takenSeatIdsByDateLeg' => $allPending->groupBy(fn (SeatReservation $r) => $r->travel_date->toDateString().'|'.$r->leg)
                ->map(fn ($g) => $g->pluck('bus_unit_seat_id')->unique()->values()),
            'standingSeatIds' => $standingSeatIds,
            // This branch only ever runs when NO real trip exists yet for
            // $date (see the showOpenTrip() branch above) — so there's
            // never anything "already synced" to show here anymore.
            'syncedForDate' => collect(),
            'hasPendingForDate' => $pendingForDate->isNotEmpty(),
            'seatHolders' => $this->seatHolderLabels($guide->pendingSeatReservations()->whereDate('travel_date', $date->toDateString())->with('user')->get()),
        ]);
    }

    /**
     * Seat picker for a date whose real trip already exists — ported
     * verbatim from AdminSeatReservationController::show() (kept alive
     * in parallel during the Apartar-asientos/Guías unification), just
     * reached from the guide's own URL instead of a separate screen.
     * Renders the SAME admin.asientos.show view used today — unifying
     * the two templates into one is a later, lower-risk pass once every
     * action (store, sendTicket, etc.) has moved over too.
     */
    /**
     * Who holds each seat, for the hover tooltip on the seat map:
     * seat id => ["NOMBRE · Categoría · Pagado|Pendiente", ...] (a seat can
     * carry an ida AND a regreso). Payments that failed/were refunded don't
     * hold a seat.
     *
     * @param  \Illuminate\Support\Collection<int, SeatReservation>  $reservations
     * @return array<int, array<int, string>>
     */
    private function seatHolderLabels($reservations): array
    {
        return $reservations
            ->reject(fn (SeatReservation $r) => in_array($r->payment_status, [
                SeatReservation::PAYMENT_FAILED,
                SeatReservation::PAYMENT_REFUNDED,
                SeatReservation::PAYMENT_CHARGEBACK,
            ], true))
            ->groupBy('bus_unit_seat_id')
            ->map(fn ($rows) => $rows->map(fn (SeatReservation $r) => $r->customer_display_name
                .' · '.$r->trip_type_label
                .' · '.($r->isPaymentCompleted() ? 'Pagado' : 'Pendiente'))->values()->all())
            ->all();
    }

    private function showOpenTrip(TripGuide $guide, LandingRoute $landingRoute): View
    {
        $landingRoute->load('busUnit.seats', 'prices');

        if (! $landingRoute->hasEnded()) {
            // Best-effort: this opportunistic sync runs on every GET, so a
            // failure (read-only DB connection, transient hiccup) must not
            // break viewing the page — worst case the self-heal just waits
            // for the next successful visit, or a write path, to catch up.
            try {
                StandingReservation::applyToTrip($landingRoute);
                // Catches up anything still staged (landing_route_id null)
                // for this date — normally nothing, since every path that
                // opens a trip already calls linkTrip() right away, but this
                // makes the page self-healing instead of needing a manual
                // "Sincronizar" button for the rare case something was missed.
                TripGuide::linkTrip($landingRoute);
            } catch (\Throwable $e) {
                Log::warning('Guía show: opportunistic sync failed for trip '.$landingRoute->id.' — '.$e->getMessage());
            }
        }

        $apartadoScope = fn ($q) => $q
            ->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
            ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email'));

        $rootScope = fn ($q) => $q->where(fn ($q3) => $q3->whereNull('notes')->orWhere('notes', 'not like', 'group:%'));

        $allReservations = $apartadoScope($landingRoute->seatReservations())->get(['id', 'bus_unit_seat_id', 'status', 'notes', 'trip_type', 'leg']);
        $reservationsBySeat = $allReservations->groupBy('bus_unit_seat_id');
        $returnLegSeatIds = $allReservations->where('leg', SeatReservation::LEG_RETURN)->pluck('bus_unit_seat_id')->unique()->values();
        $outboundBySeat = $allReservations->where('leg', '!=', SeatReservation::LEG_RETURN)->groupBy('bus_unit_seat_id');
        $roots = $allReservations->filter(fn (SeatReservation $r) => $r->notes === null || ! str_starts_with((string) $r->notes, 'group:'));
        $pendingCount = $roots->where('status', SeatReservation::STATUS_PENDING)->count();
        $sentCount = $roots->where('status', SeatReservation::STATUS_SENT)->count();

        $filters = request()->only(['q', 'paid', 'trip_type', 'payment_method']);
        $reservations = $rootScope($apartadoScope($landingRoute->seatReservations()))
            ->listFilters($filters)
            ->with(['reservedBy'])
            ->orderBy('created_at')
            ->paginate(12)
            ->withQueryString();

        $groupSeatsByNote = $apartadoScope($landingRoute->seatReservations())
            ->whereIn('notes', $reservations->map(fn (SeatReservation $r) => 'group:'.$r->id))
            ->with('seat')
            ->get()
            ->groupBy('notes');
        $reservations->getCollection()->each(function (SeatReservation $r) use ($groupSeatsByNote) {
            $r->setRelation('groupSeats', $groupSeatsByNote->get('group:'.$r->id, collect()));
        });

        $takenIds = $landingRoute->seatReservations()
            ->whereNull('customer_name')
            ->whereNull('customer_email')
            ->whereNotNull('user_id')
            ->pluck('bus_unit_seat_id');

        $releasedSeatIds = $landingRoute->seatReservations()
            ->whereNotNull('return_released_at')
            ->whereNull('resold_return_reservation_id')
            ->where('return_resale_expires_at', '>', now())
            ->pluck('bus_unit_seat_id');

        $idaOnlySeatIds = SeatReservation::idaOnlySeatIdsFor($landingRoute);

        $standingSeatIds = StandingReservation::activeSeatIdsFor($landingRoute->from, $landingRoute->to, $landingRoute->bus_unit_id, $landingRoute->id, $landingRoute->day?->dayOfWeek);

        $standingBySeat = StandingReservation::where('from', $landingRoute->from)
            ->where('to', $landingRoute->to)
            ->where('bus_unit_id', $landingRoute->bus_unit_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('bus_unit_seat_id');

        $corteService = app(TripCorteService::class);
        $corte = $corteService->summarize($landingRoute);

        return view('admin.asientos.show', [
            'corte' => $corte,
            'corteSaved' => TripCorte::where('landing_route_id', $landingRoute->id)->first(),
            'contratosCompras' => $corteService->contratosCompras(),
            'trip' => $landingRoute,
            'guide' => $guide,
            'reservations' => $reservations,
            'filters' => $filters,
            'reservationsBySeat' => $reservationsBySeat,
            'pendingCount' => $pendingCount,
            'sentCount' => $sentCount,
            'takenIds' => $takenIds,
            'returnLegSeatIds' => $returnLegSeatIds,
            'outboundBySeat' => $outboundBySeat,
            'releasedSeatIds' => $releasedSeatIds,
            'idaOnlySeatIds' => $idaOnlySeatIds,
            'customers' => Customer::orderBy('name')->get(['name', 'phone', 'email']),
            'currentBusSeats' => $landingRoute->busUnit->seats()->bookable()->get()->sortBy('label', SORT_NATURAL)->values(),
            'takenSeatIdsByLeg' => $landingRoute->seatReservations()
                ->get(['bus_unit_seat_id', 'leg'])
                ->groupBy('leg')
                ->map(fn ($g) => $g->pluck('bus_unit_seat_id')->unique()->values()),
            'standingSeatIds' => $standingSeatIds,
            'standingBySeat' => $standingBySeat,
            'seatHolders' => $this->seatHolderLabels($landingRoute->seatReservations()->with('user')->get()),
            'busUnits' => BusUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeReservation(Request $request, TripGuide $guide, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        // With a real trip open for the date, its own plantilla (which
        // changeTripBus() may have switched) is what the seat picker shows,
        // not the guide's — validate the seats against that bus.
        $requestedDate = $request->date('travel_date') ?? now();
        $seatBusUnitId = $this->matchingTrip($guide, $requestedDate->copy()->startOfDay())?->bus_unit_id
            ?? $guide->busUnitIdFor($requestedDate);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            // Only meaningful for 'especial' (same price: ida, redondo or regreso).
            'especial_mode' => ['nullable', 'string', 'in:one_way,round_trip,return'],
            'travel_date' => ['required', 'date', 'after_or_equal:'.$guide->date_from->toDateString(), 'before_or_equal:'.$guide->date_to->toDateString()],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $seatBusUnitId),
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
            // "regreso" only, and only meaningful once a real trip exists
            // for $travel_date (see storeOnOpenTrip()) — a different day
            // than that trip's own return date splits it off into the
            // guide for whichever trip actually comes back that day.
            'regreso_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $date = Carbon::parse($data['travel_date'])->startOfDay();
        $dateBusUnitId = $guide->busUnitIdFor($date);

        // A real trip already exists for this date — sell against it
        // directly (full price, real ticket/QR, abono, mancuerna, "de
        // planta" — same as the old Apartar asientos store()), instead
        // of staging an estimate that would just get migrated later.
        if ($openTrip = $this->matchingTrip($guide, $date)) {
            return $this->storeOnOpenTrip($request, $guide, $openTrip, $data, $whatsapp, $ticketImages);
        }

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
            ? TripTicketPrice::estimateUnitPrice($guide->from, $guide->to, $dateBusUnitId, TripTicketPrice::TYPE_REGRESO)
            : 0.0;
        // Same reasoning as $regresoPrice above — needed so the immediate
        // WhatsApp confirmation below can show an actual total instead of
        // $0 while this apartado waits on a real trip to price off of.
        $estimatedUnitPrice = TripTicketPrice::estimateUnitPrice($guide->from, $guide->to, $dateBusUnitId, $data['trip_type'], count($data['seat_ids']));

        $tripType = $data['trip_type'];
        $legAttrs = SeatReservation::legAttributesFor($tripType, $data['especial_mode'] ?? null);
        $leg = $legAttrs['leg'];

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
            $root = DB::transaction(function () use ($guide, $data, $request, $tripType, $leg, $legAttrs, $date, $isCashPending, $methodsBySeat, $returnDatesBySeat, $returnPaidBySeat, $allowsReturnDate, $regresoPrice, $estimatedUnitPrice) {
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
                        'especial_mode' => $legAttrs['especial_mode'],
                        'leg' => $leg,
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
                                'bus_unit_id' => $dateBusUnitId,
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
        $noticeFailed = false;
        foreach ($roots as $groupRoot) {
            try {
                $whatsapp->sendReservationNotice($groupRoot->groupMembers());
            } catch (\Throwable $e) {
                Log::warning('Guía reservation-confirmation notice failed for reservation '.$groupRoot->id.': '.$e->getMessage());
                $noticeFailed = true;
            }
        }

        // Trip already open for this date: attach right away.
        if ($openTrip = $this->matchingTrip($guide, $date)) {
            TripGuide::linkTrip($openTrip);
        }

        $seatCount = count($data['seat_ids']);
        $summary = "Apartado en guía creado para {$data['customer_name']} ({$seatCount} asiento".($seatCount === 1 ? '' : 's').", {$date->format('d/m/Y')}). Se vinculará al viaje real en cuanto se abra.";

        // Silent failure before: the admin saw "creado" even when the
        // customer never got the WhatsApp confirmation.
        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()])
            ->with($noticeFailed ? 'warning' : 'success', $noticeFailed
                ? $summary.' ⚠ No se pudo enviar el aviso por WhatsApp — avísale al cliente (revisa Administración → WhatsApp).'
                : $summary);
    }

    /**
     * Sells directly against a real, already-open trip — ported from
     * AdminSeatReservationController::store(), reached from the guide's
     * own URL instead of a separate screen (see storeReservation()).
     * Full feature parity with the old Apartar asientos: abono, mancuerna
     * pricing, "de planta", "ya sé cuándo regresa", transfer reference,
     * double-booking/zone-restriction guards.
     *
     * Notification timing is the one deliberate change from the old
     * store(): instead of always sending the real ticket/payment-reminder
     * right away, it only does that when the trip is imminent (today or
     * tomorrow — the "día antes" moment has already arrived or there's no
     * future one to wait for). Otherwise this apartado only gets the
     * plain "quedó reservado" notice now, same as a guide-staged one —
     * SendDayBeforeReminders sends the real ticket or payment reminder
     * the day before, whenever that turns out to be.
     */
    private function storeOnOpenTrip(Request $request, TripGuide $guide, LandingRoute $openTrip, array $data, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if ($openTrip->hasEnded()) {
            return back()->withInput()->with('error', 'Este viaje ya pasó — ya no se pueden crear apartados.');
        }

        Customer::remember($data['customer_name'], $data['customer_phone'], $data['customer_email'] ?? null);

        $tripType = $data['trip_type'];
        $legAttrs = SeatReservation::legAttributesFor($tripType, $data['especial_mode'] ?? null);
        $isPaid = (bool) $data['paid'];
        $methodsBySeat = $data['payment_method'];
        if ($tripType === TripTicketPrice::TYPE_ESPECIAL) {
            $sharedMethod = reset($methodsBySeat);
            $methodsBySeat = array_map(fn () => $sharedMethod, $methodsBySeat);
        }
        $distinctMethods = array_values(array_unique($methodsBySeat));
        $isCashPending = ! $isPaid;
        $unitPrice = $openTrip->seatPriceFor($tripType, count($data['seat_ids']));
        $returnDatesBySeat = array_filter($data['return_date'] ?? []);
        $returnPaidBySeat = $data['return_paid'] ?? [];
        $allowsReturnDate = in_array($tripType, [
            TripTicketPrice::TYPE_ONE_WAY,
            TripTicketPrice::TYPE_ROUND_TRIP,
            TripTicketPrice::TYPE_ESPECIAL,
        ], true);
        $regresoPrice = $allowsReturnDate
            ? (float) ($openTrip->priceFor(TripTicketPrice::TYPE_REGRESO)?->price ?? 0)
            : 0.0;

        if ($tripType === TripTicketPrice::TYPE_REGRESO && ! empty($data['regreso_date'])) {
            $returnDate = Carbon::parse($data['regreso_date'])->startOfDay();
            if (! $openTrip->return_date?->isSameDay($returnDate)) {
                return $this->stageRegresoInGuide($request, $guide, $openTrip, $data, $returnDate, $unitPrice, $isCashPending, $methodsBySeat);
            }
        }

        $transferReference = null;
        if ($isCashPending && in_array(SeatReservation::PAYMENT_METHOD_TRANSFER, $distinctMethods, true)) {
            $transferReference = SeatReservation::generateTransferReference(
                $openTrip->day ?? now(),
                count($data['seat_ids']),
                $data['customer_name']
            );
        }

        $blockingReservations = $openTrip->seatReservations()
            ->whereIn('bus_unit_seat_id', $data['seat_ids'])
            ->where(function ($q) {
                $q->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
                    ->orWhereNotNull('user_id');
            })
            ->get()
            ->reject(fn (SeatReservation $r) => $r->leg === SeatReservation::LEG_RETURN
                && match ($tripType) {
                    TripTicketPrice::TYPE_ONE_WAY => true,
                    TripTicketPrice::TYPE_ROUND_TRIP, TripTicketPrice::TYPE_ESPECIAL => ! empty($returnDatesBySeat[$r->bus_unit_seat_id]),
                    default => false,
                });

        $idaOnlySeatIds = $tripType === TripTicketPrice::TYPE_REGRESO
            ? SeatReservation::idaOnlySeatIdsFor($openTrip)
            : collect();

        $alreadyTaken = $blockingReservations
            ->reject(fn (SeatReservation $r) => $tripType === TripTicketPrice::TYPE_REGRESO
                && ($r->isResaleWindowOpen() || $idaOnlySeatIds->contains((int) $r->bus_unit_seat_id)))
            ->pluck('bus_unit_seat_id')
            ->unique()
            ->all();

        if (! empty($alreadyTaken)) {
            $labels = BusUnitSeat::whereIn('id', $alreadyTaken)->pluck('label')->all();

            return back()->withInput()->with('error', 'Los siguientes asientos ya están apartados: '.implode(', ', $labels));
        }

        $mismatched = $openTrip->busUnit->seats()
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
            $root = DB::transaction(function () use ($openTrip, $data, $request, $tripType, $legAttrs, $unitPrice, $isCashPending, $methodsBySeat, $transferReference, $blockingReservations, $returnDatesBySeat, $returnPaidBySeat, $allowsReturnDate, $regresoPrice) {
                $root = null;
                $abonoLeft = (float) ($data['abono'] ?? 0);
                foreach ($data['seat_ids'] as $seatId) {
                    $seatPrice = (float) $unitPrice;
                    $seatAbono = min($abonoLeft, $seatPrice);
                    $abonoLeft -= $seatAbono;
                    $seatCovered = $isCashPending && $seatPrice > 0 && $seatAbono >= $seatPrice;
                    $seatPending = $isCashPending && ! $seatCovered;
                    $new = SeatReservation::create([
                        'landing_route_id' => $openTrip->id,
                        'bus_unit_seat_id' => $seatId,
                        'user_id' => null,
                        'trip_type' => $tripType,
                        'especial_mode' => $legAttrs['especial_mode'],
                        'leg' => $legAttrs['leg'],
                        'unit_price' => $unitPrice,
                        'abono' => $seatAbono,
                        'customer_name' => $data['customer_name'],
                        'customer_email' => $data['customer_email'] ?? null,
                        'customer_phone' => $data['customer_phone'],
                        'status' => SeatReservation::STATUS_PENDING,
                        'payment_method' => $methodsBySeat[$seatId] ?? reset($methodsBySeat),
                        'payment_status' => $seatPending ? SeatReservation::PAYMENT_PENDING : SeatReservation::PAYMENT_COMPLETED,
                        'paid_at' => $seatPending ? null : now(),
                        'transfer_reference' => $root ? null : $transferReference,
                        'transfer_expires_at' => ! $root && $transferReference ? now()->addDays(3) : null,
                        'reserved_by' => $request->user()?->id,
                        'notes' => $root ? 'group:'.$root->id : ($data['notes'] ?? null),
                    ]);

                    if (! empty($data['mark_as_standing']) && $openTrip->day !== null) {
                        StandingReservation::markStanding(
                            [
                                'from' => $openTrip->from,
                                'to' => $openTrip->to,
                                'bus_unit_id' => $openTrip->bus_unit_id,
                                'bus_unit_seat_id' => $seatId,
                            ],
                            [
                                'customer_name' => $data['customer_name'],
                                'customer_phone' => $data['customer_phone'],
                                'customer_email' => $data['customer_email'] ?? null,
                                'trip_type' => $tripType,
                                'notes' => $data['notes'] ?? null,
                            ],
                            $openTrip->day->dayOfWeek
                        );

                        StandingReservation::provisionForRoute($openTrip->from, $openTrip->to, $openTrip->bus_unit_id);
                    }

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

                    if ($tripType === TripTicketPrice::TYPE_REGRESO) {
                        $released = $blockingReservations->first(fn (SeatReservation $r) => (int) $r->bus_unit_seat_id === (int) $seatId && $r->isResaleWindowOpen());
                        $released?->update(['resold_return_reservation_id' => $new->id]);
                    }

                    $root ??= $new;
                }

                return $root;
            });
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage() ?: 'No se pudo agendar el regreso para uno de los asientos.');
        }

        // Imminent (today/tomorrow): no future "día antes" moment to wait
        // for, so send the full notice now, same as the trip opening this
        // late always has — otherwise only the plain reservation notice,
        // and SendDayBeforeReminders sends the real ticket/reminder when
        // that day actually arrives (see the method docblock above).
        $isImminent = $openTrip->day !== null && $openTrip->day->lte(now()->addDay()->startOfDay());
        $allSent = true;
        foreach ($root->regroupByPayment() as $groupRoot) {
            $group = $groupRoot->groupMembers()->load(['landingRoute', 'seat']);
            if ($isImminent) {
                $allSent = $whatsapp->deliverGroup($group, $ticketImages) && $allSent;
                SeatReservation::whereIn('id', $group->pluck('id'))->update(['reminder_sent_at' => now()]);
            } else {
                try {
                    $whatsapp->sendReservationNotice($group);
                } catch (\Throwable $e) {
                    Log::warning('Reservation-confirmation notice failed for reservation '.$groupRoot->id.': '.$e->getMessage());
                    $allSent = false;
                }
            }
        }

        $seatCount = count($data['seat_ids']);
        $tripTypeLabel = TripTicketPrice::tripTypes()[$tripType] ?? $tripType;
        $plural = $seatCount > 1 ? 's' : '';
        $summary = "{$data['customer_name']} ({$seatCount} asiento{$plural}, {$tripTypeLabel})";
        $redirect = redirect()->route('admin.guias.show', [$guide, 'fecha' => $openTrip->day->toDateString()]);

        if (! $isImminent) {
            return $redirect->with($allSent ? 'success' : 'error', $allSent
                ? "Apartado creado para {$summary}. El cliente recibirá un aviso por WhatsApp; el boleto (o el aviso de pago al abordar) se manda un día antes del viaje."
                : "Apartado creado para {$summary}, pero no se pudo enviar el aviso por WhatsApp automáticamente.");
        }

        if ($isCashPending) {
            $methodLabel = implode(' y ', array_map(
                fn ($m) => (new SeatReservation(['payment_method' => $m]))->payment_method_label,
                $distinctMethods
            ));
            $activationHint = in_array(SeatReservation::PAYMENT_METHOD_TRANSFER, $distinctMethods, true)
                ? 'Valida la transferencia en Pagos para mandar el boleto con QR.'
                : 'El boleto se imprime/envía cuando confirmes el pago en Pagos.';

            return $redirect->with($allSent ? 'success' : 'error', $allSent
                ? "Apartado creado para {$summary}, {$methodLabel}. El cliente recibirá un aviso por WhatsApp. {$activationHint}"
                : "Apartado creado para {$summary}, {$methodLabel}, pero no se pudo enviar el aviso por WhatsApp automáticamente. {$activationHint}");
        }

        return $redirect->with($allSent ? 'success' : 'error', $allSent
            ? "Apartado creado y boleto{$plural} enviado{$plural} por WhatsApp a {$summary}."
            : "Apartado creado para {$summary}, pero no se pudo enviar el boleto por WhatsApp automáticamente.");
    }

    /**
     * "Regreso otro día" sobre un viaje real ya abierto: el pasajero
     * regresa un día distinto al de este viaje, así que se agenda en la
     * guía del viaje que sí regresa ese día — ported from
     * AdminSeatReservationController::stageRegresoInGuide(). No manda
     * WhatsApp aquí; el boleto sale cuando ese viaje real se abra y
     * TripGuide::linkTrip() lo vincule.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int|string, string>  $methodsBySeat
     */
    private function stageRegresoInGuide(Request $request, TripGuide $guide, LandingRoute $openTrip, array $data, Carbon $returnDate, float $unitPrice, bool $isCashPending, array $methodsBySeat): RedirectResponse
    {
        try {
            [$root, $slot] = DB::transaction(function () use ($request, $openTrip, $data, $returnDate, $unitPrice, $isCashPending, $methodsBySeat) {
                $slot = SeatReservation::regresoGuideSlot($openTrip, $returnDate, $data['seat_ids']);

                $root = null;
                foreach ($data['seat_ids'] as $seatId) {
                    $new = SeatReservation::create([
                        'trip_guide_id' => $slot['guide']->id,
                        'travel_date' => $slot['day']->toDateString(),
                        'bus_unit_seat_id' => $slot['seat_map'][(int) $seatId],
                        'trip_type' => TripTicketPrice::TYPE_REGRESO,
                        'leg' => SeatReservation::LEG_RETURN,
                        'unit_price' => $unitPrice,
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
                    $root ??= $new;
                }

                return [$root, $slot];
            });

            $root->regroupByPayment();
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage() ?: 'No se pudo agendar el regreso en la guía.');
        }

        $seatCount = count($data['seat_ids']);
        $plural = $seatCount > 1 ? 's' : '';

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $openTrip->day->toDateString()])
            ->with('success', "Regreso agendado en la guía para {$data['customer_name']} ({$seatCount} asiento{$plural}): regresa el {$returnDate->format('d/m/Y')}, en el viaje que sale el {$slot['day']->format('d/m/Y')}. Se vinculará y se enviará el boleto en cuanto se abra ese viaje.");
    }

    /**
     * Cancels a whole apartado group — ported from
     * AdminSeatReservationController::destroy() for a reservation already
     * on a real trip (blocked once the trip ended), or the original
     * guide-only cancel (hard delete, nothing to free since it was never
     * really booked) when it's still staged.
     */
    public function destroyReservation(TripGuide $guide, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->trip_guide_id === $guide->id || $reservation->landing_route_id !== null, 404);

        $group = $reservation->groupMembers();

        if ($reservation->landing_route_id !== null) {
            $trip = $reservation->landingRoute;
            if ($trip->hasEnded()) {
                return back()->with('error', 'Este viaje ya pasó — ya no se puede cancelar este apartado.');
            }

            SeatReservation::whereIn('id', $group->pluck('id'))->delete();

            return redirect()
                ->route('admin.guias.show', [$guide, 'fecha' => $trip->day?->toDateString()])
                ->with('success', $group->count() > 1
                    ? 'Apartado cancelado ('.$group->count().' asientos). Los asientos vuelven a estar disponibles.'
                    : 'Apartado cancelado. El asiento vuelve a estar disponible.');
        }

        $date = $reservation->travel_date;
        SeatReservation::whereIn('id', $group->pluck('id'))->delete();

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $date?->toDateString()])
            ->with('success', $group->count() > 1
                ? 'Apartado de guía cancelado ('.$group->count().' asientos).'
                : 'Apartado de guía cancelado.');
    }

    /**
     * Manual fix for a mismatched seat — either a guide apartado left
     * "huérfano" by update() switching the guide's bus unit (remapSeatsTo()),
     * or a guide-linked ticket TripGuide::linkTrip() couldn't remap by
     * label onto the trip's actual bus. Ported from
     * AdminSeatReservationController::reassignSeat() for the real-trip
     * case (re-dispatches the notification linkTrip() would have sent
     * once every seat in the group is fixed).
     */
    public function reassignSeat(Request $request, TripGuide $guide, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->trip_guide_id === $guide->id || $reservation->landing_route_id !== null, 404);

        $busUnitId = $reservation->landing_route_id !== null
            ? $reservation->landingRoute->bus_unit_id
            : $guide->busUnitIdFor($reservation->travel_date);

        $data = $request->validate([
            'bus_unit_seat_id' => [
                'required',
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $busUnitId),
            ],
        ]);

        if ($reservation->landing_route_id !== null) {
            $taken = SeatReservation::where('landing_route_id', $reservation->landing_route_id)
                ->where('id', '!=', $reservation->id)
                ->where('bus_unit_seat_id', $data['bus_unit_seat_id'])
                ->where('leg', $reservation->leg)
                ->exists();

            if ($taken) {
                return back()->with('error', 'Ese asiento ya está apartado en este viaje.');
            }

            $reservation->update(['bus_unit_seat_id' => $data['bus_unit_seat_id']]);

            $group = $reservation->groupMembers();
            if ($group->whereNull('ticket_sent_at')->isNotEmpty()) {
                \App\Jobs\SendLinkedTicketNotification::dispatch($reservation->groupRootId());
            }

            return redirect()
                ->route('admin.guias.show', [$guide, 'fecha' => $reservation->landingRoute->day?->toDateString()])
                ->with('success', 'Asiento reasignado correctamente.');
        }

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

    /**
     * Manual retry for when the automatic send in storeOnOpenTrip() (or
     * the day-before job) didn't go through — ported from
     * AdminSeatReservationController::sendTicket(). Only ever applies to
     * a reservation already on a real trip; a still-staged one has
     * nothing to send yet.
     */
    public function sendTicket(TripGuide $guide, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        abort_unless($reservation->landing_route_id !== null, 404);
        $trip = $reservation->landingRoute;

        if ($trip->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se pueden enviar boletos.');
        }

        if (! $reservation->isPending()) {
            return back()->with('error', 'Este apartado ya no está pendiente.');
        }

        $group = $reservation->groupMembers()->load(['landingRoute', 'seat']);

        if ($group->contains(fn (SeatReservation $r) => $r->hasSeatMismatch())) {
            return back()->with('error', 'Uno o más asientos de este apartado no corresponden al autobús de este viaje — reasígnalos abajo antes de enviar.');
        }

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
     * "No viaja hoy" — ported from
     * AdminSeatReservationController::releaseStanding(). Only applies to
     * a "de planta" seat already auto-booked on a real trip for this
     * date (needs $fecha since a guide spans many dates/trips).
     */
    public function releaseStanding(Request $request, TripGuide $guide, StandingReservation $standing): RedirectResponse
    {
        $date = Carbon::parse($request->validate(['fecha' => ['required', 'date']])['fecha'])->startOfDay();
        $trip = $this->matchingTrip($guide, $date);

        abort_unless(
            $trip
                && $standing->bus_unit_id === $trip->bus_unit_id
                && $standing->from === $trip->from
                && $standing->to === $trip->to,
            404
        );

        $standing->skips()->firstOrCreate(['landing_route_id' => $trip->id]);

        $trip->seatReservations()
            ->where('bus_unit_seat_id', $standing->bus_unit_seat_id)
            ->where('notes', 'like', 'Asiento de planta%')
            ->delete();

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()])
            ->with('success', 'Asiento liberado para este viaje — seguirá apartándose solo en los demás.');
    }

    /**
     * Drop ONE seat out of a multi-seat apartado — ported from
     * AdminSeatReservationController::removeSeat(). Only applies to a
     * reservation already on a real trip.
     */
    public function removeSeat(TripGuide $guide, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        abort_unless($reservation->landing_route_id !== null, 404);
        $trip = $reservation->landingRoute;

        if ($trip->hasEnded()) {
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

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $trip->day?->toDateString()])
            ->with($resent ? 'success' : 'error', $resent
                ? $message.' Se reenvió la información actualizada por WhatsApp.'
                : $message.' No se pudo reenviar la información por WhatsApp automáticamente.');
    }

    /**
     * Edits an existing real-trip apartado's category/payment — ported
     * from AdminSeatReservationController::updateCategory(). Only
     * applies to a reservation already on a real trip.
     */
    public function updateCategory(Request $request, TripGuide $guide, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        abort_unless($reservation->landing_route_id !== null, 404);
        $trip = $reservation->landingRoute;

        if ($trip->hasEnded()) {
            return back()->with('error', 'Este viaje ya pasó — ya no se puede editar el apartado.');
        }

        if ($reservation->isFullyCheckedIn()) {
            return back()->with('error', 'No se puede editar un apartado ya verificado.');
        }

        $data = $request->validate([
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            'especial_mode' => ['nullable', 'string', 'in:one_way,round_trip,return'],
            'payment_method' => ['required', 'string', 'in:transfer,cash,card,tbd'],
            'payment_status' => ['required', 'string', 'in:pending,completed'],
            'regreso_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $tripType = $data['trip_type'];
        $paid = $data['payment_status'] === SeatReservation::PAYMENT_COMPLETED;

        if ($tripType === TripTicketPrice::TYPE_REGRESO && ! empty($data['regreso_date'])) {
            $returnDate = Carbon::parse($data['regreso_date'])->startOfDay();
            if (! $trip->return_date?->isSameDay($returnDate)) {
                return $this->moveRegresoToGuide($guide, $trip, $reservation, $data, $returnDate);
            }
        }
        $before = $reservation->trip_type.'|'.$reservation->payment_method.'|'.$reservation->payment_status;

        $reservation->update(SeatReservation::legAttributesFor($tripType, $data['especial_mode'] ?? null) + [
            'trip_type' => $tripType,
            'unit_price' => $trip->seatPriceFor($tripType),
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            'paid_at' => $paid ? ($reservation->paid_at ?? now()) : null,
            'status' => $paid ? $reservation->status : SeatReservation::STATUS_PENDING,
        ]);

        $especialMembers = $reservation->fresh()->groupMembers()
            ->where('trip_type', TripTicketPrice::TYPE_ESPECIAL);
        if ($especialMembers->isNotEmpty()) {
            $mancuerna = ['unit_price' => $trip->seatPriceFor(TripTicketPrice::TYPE_ESPECIAL, $especialMembers->count())];
            if ($reservation->isEspecial()) {
                $mancuerna += $reservation->only(['payment_method', 'payment_status', 'paid_at', 'status']);
            }
            SeatReservation::whereIn('id', $especialMembers->pluck('id'))->update($mancuerna);
            $reservation->refresh();
        }

        $roots = $reservation->regroupByPayment();

        $changed = $before !== $reservation->trip_type.'|'.$reservation->payment_method.'|'.$reservation->payment_status;
        $resent = null;
        if ($changed) {
            // Reenviar TODOS los boletos del cliente (no solo el editado):
            // regroupByPayment() pudo partir el apartado en varios grupos.
            $resent = true;
            $groups = $roots->map(fn (SeatReservation $r) => $r->fresh()->groupMembers()->load(['landingRoute', 'seat']));
            if ($groups->flatten()->whereNotNull('ticket_sent_at')->isNotEmpty()) {
                foreach ($groups as $members) {
                    $resent = $whatsapp->deliverGroup($members, $ticketImages) && $resent;
                }
            } else {
                $resent = null;
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

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $trip->day?->toDateString()])
            ->with($resent === false ? 'error' : 'success', 'Boleto actualizado. '.$summary.'.'.$notice);
    }

    /**
     * Edita categoría/pago de un apartado que sigue en la guía (aún sin
     * viaje abierto) — antes solo se podía editar con el viaje abierto.
     * Cambia solo ese asiento (ej. 3 redondos y uno no regresa → "Solo
     * ida"). Avisa por WhatsApp solo de ese asiento.
     */
    public function updateStagedCategory(Request $request, TripGuide $guide, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp): RedirectResponse
    {
        abort_unless($reservation->landing_route_id === null && $reservation->trip_guide_id === $guide->id, 404);

        if ($reservation->travel_date?->lt(now()->startOfDay())) {
            return back()->with('error', 'Esa fecha ya pasó — ya no se puede editar el apartado.');
        }

        $data = $request->validate([
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            'especial_mode' => ['nullable', 'string', 'in:one_way,round_trip,return'],
            'payment_method' => ['required', 'string', 'in:transfer,cash,card,tbd'],
            'payment_status' => ['required', 'string', 'in:pending,completed'],
        ]);

        $tripType = $data['trip_type'];
        $paid = $data['payment_status'] === SeatReservation::PAYMENT_COMPLETED;
        $route = $reservation->outboundRouteInfo();
        $groupIds = $reservation->groupMembers()->pluck('id');

        $reservation->update(SeatReservation::legAttributesFor($tripType, $data['especial_mode'] ?? null) + [
            'trip_type' => $tripType,
            'unit_price' => TripTicketPrice::estimateUnitPrice($route['from'], $route['to'], (int) $route['bus_unit_id'], $tripType, 1),
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            'paid_at' => $paid ? ($reservation->paid_at ?? now()) : null,
            'status' => $paid ? $reservation->status : SeatReservation::STATUS_PENDING,
        ]);

        $especialMembers = $reservation->fresh()->groupMembers()->where('trip_type', TripTicketPrice::TYPE_ESPECIAL);
        if ($especialMembers->isNotEmpty()) {
            $mancuerna = ['unit_price' => TripTicketPrice::estimateUnitPrice($route['from'], $route['to'], (int) $route['bus_unit_id'], TripTicketPrice::TYPE_ESPECIAL, $especialMembers->count())];
            if ($reservation->isEspecial()) {
                $mancuerna += $reservation->only(['payment_method', 'payment_status', 'paid_at', 'status']);
            }
            SeatReservation::whereIn('id', $especialMembers->pluck('id'))->update($mancuerna);
            $reservation->refresh();
        }

        $reservation->regroupByPayment();

        // Aviso con TODOS los asientos del cliente, con la corrección aplicada.
        $summary = 'Apartado actualizado: asiento '.($reservation->seat?->label ?? '—').' → '.(TripTicketPrice::tripTypes()[$tripType] ?? $tripType).'.';
        $noticeFailed = false;
        try {
            $allSeats = SeatReservation::whereIn('id', $groupIds)->orderBy('id')->get();
            $whatsapp->sendReservationNotice($allSeats, true);
        } catch (\Throwable $e) {
            Log::warning('Guía edit notice failed for reservation '.$reservation->id.': '.$e->getMessage());
            $noticeFailed = true;
        }

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $reservation->travel_date?->toDateString()])
            ->with($noticeFailed ? 'error' : 'success', $noticeFailed
                ? $summary.' ⚠ No se pudo enviar el aviso por WhatsApp — avísale al cliente.'
                : $summary.' Se envió el aviso por WhatsApp.');
    }

    /**
     * Editing an existing "regreso" to a different return day — ported
     * from AdminSeatReservationController::moveRegresoToGuide().
     *
     * @param  array<string, mixed>  $data
     */
    private function moveRegresoToGuide(TripGuide $guide, LandingRoute $trip, SeatReservation $reservation, array $data, Carbon $returnDate): RedirectResponse
    {
        $paid = $data['payment_status'] === SeatReservation::PAYMENT_COMPLETED;
        $seatLabel = $reservation->seat?->label ?? '—';

        try {
            $slot = DB::transaction(function () use ($trip, $reservation, $data, $returnDate, $paid) {
                $slot = SeatReservation::regresoGuideSlot($trip, $returnDate, [$reservation->bus_unit_seat_id], $reservation->id);

                $reservation->detachFromGroup();

                SeatReservation::where('resold_return_reservation_id', $reservation->id)
                    ->update(['resold_return_reservation_id' => null]);

                $reservation->update([
                    'landing_route_id' => null,
                    'trip_guide_id' => $slot['guide']->id,
                    'travel_date' => $slot['day']->toDateString(),
                    'bus_unit_seat_id' => $slot['seat_map'][(int) $reservation->bus_unit_seat_id],
                    'trip_type' => TripTicketPrice::TYPE_REGRESO,
                    'leg' => SeatReservation::LEG_RETURN,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $data['payment_status'],
                    'paid_at' => $paid ? ($reservation->paid_at ?? now()) : null,
                    'status' => SeatReservation::STATUS_PENDING,
                ]);

                return $slot;
            });

            $reservation->fresh()->regroupByPayment();
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage() ?: 'No se pudo cambiar la fecha del regreso.');
        }

        return redirect()
            ->route('admin.guias.show', [$guide, 'fecha' => $trip->day?->toDateString()])
            ->with('success', "Asiento {$seatLabel}: regreso cambiado al {$returnDate->format('d/m/Y')} (viaje que sale el {$slot['day']->format('d/m/Y')}). Quedó en la guía y se enviará el boleto en cuanto se abra ese viaje.");
    }

    /**
     * Printable passenger manifest — ported from
     * AdminSeatReservationController::manifest(). Needs $fecha since a
     * guide spans many dates/trips.
     */
    public function manifest(Request $request, TripGuide $guide): Response
    {
        $date = Carbon::parse($request->validate(['fecha' => ['required', 'date']])['fecha'])->startOfDay();
        $trip = $this->matchingTrip($guide, $date);
        abort_unless($trip && $trip->hasSeatMap(), 404);

        $reservationsBySeat = $trip->seatReservations()
            ->where(fn ($q) => $q->whereNull('payment_status')->orWhereNotIn('payment_status', [
                SeatReservation::PAYMENT_FAILED,
                SeatReservation::PAYMENT_REFUNDED,
                SeatReservation::PAYMENT_CHARGEBACK,
            ]))
            ->with(['user', 'seat'])
            ->get()
            ->groupBy('bus_unit_seat_id');

        $seats = $trip->busUnit->seats()
            ->bookable()
            ->get()
            ->sortBy(fn ($seat) => $seat->label, SORT_NATURAL)
            ->values();

        $pdf = Pdf::loadView('admin.asientos.manifiesto-pdf', [
            'trip' => $trip,
            'seats' => $seats,
            'reservationsBySeat' => $reservationsBySeat,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('lista-asientos-'.$trip->id.'.pdf');
    }

    /**
     * "Disponibilidad" per-seat editor — ported from
     * AdminSeatReservationController::availability(). Needs $fecha since
     * a guide spans many dates/trips.
     */
    public function availability(Request $request, TripGuide $guide): View|RedirectResponse
    {
        $date = Carbon::parse($request->validate(['fecha' => ['required', 'date']])['fecha'])->startOfDay();
        $trip = $this->matchingTrip($guide, $date);
        abort_unless($trip && $trip->hasSeatMap(), 404, 'Este viaje no tiene un mapa de asientos configurado.');

        if ($trip->hasEnded()) {
            return redirect()
                ->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()])
                ->with('error', 'Este viaje ya pasó — ya no se puede editar la disponibilidad.');
        }

        $trip->load('busUnit.seats');

        $stats = $trip->busUnit->seats
            ->where('kind', 'seat')
            ->groupBy(fn ($seat) => $seat->allowed_trip_type ?? 'both')
            ->map->count();

        return view('admin.asientos.availability', [
            'trip' => $trip,
            'guide' => $guide,
            'stats' => [
                'both' => $stats['both'] ?? 0,
                'one_way' => $stats['one_way'] ?? 0,
                'round_trip' => $stats['round_trip'] ?? 0,
            ],
        ]);
    }

    /**
     * Bulk update of allowed_trip_type — ported from
     * AdminSeatReservationController::updateAvailability().
     */
    public function updateAvailability(Request $request, TripGuide $guide): RedirectResponse
    {
        $date = Carbon::parse($request->validate(['fecha' => ['required', 'date']])['fecha'])->startOfDay();
        $trip = $this->matchingTrip($guide, $date);
        abort_unless($trip && $trip->hasSeatMap(), 404);

        if ($trip->hasEnded()) {
            return redirect()
                ->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()])
                ->with('error', 'Este viaje ya pasó — ya no se puede editar la disponibilidad.');
        }

        $data = $request->validate([
            'changes' => ['required', 'array'],
            'changes.*.id' => [
                'required', 'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $trip->bus_unit_id),
            ],
            'changes.*.allowed_trip_type' => ['required', 'string', 'in:both,one_way,round_trip'],
        ]);

        $busUnitId = $trip->bus_unit_id;
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
            ->route('admin.guias.availability', [$guide, 'fecha' => $date->toDateString()])
            ->with('success', "Disponibilidad actualizada ({$count} asiento".($count === 1 ? '' : 's').').');
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

    /**
     * Switches the "plantilla" of ONE real trip (the guide's selected
     * date), unlike update() which changes the whole guide. Existing
     * apartados follow their seat label to the new bus.
     */
    public function changeTripBus(Request $request, TripGuide $guide): RedirectResponse
    {
        $data = $request->validate([
            'travel_date' => ['required', 'date'],
            'bus_unit_id' => ['required', 'exists:bus_units,id'],
        ]);

        $date = Carbon::parse($data['travel_date'])->startOfDay();
        $trip = $this->matchingTrip($guide, $date);
        $back = redirect()->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()]);

        if (! $trip || $trip->hasEnded()) {
            return $back->with('error', 'No hay un viaje abierto para cambiar en esa fecha.');
        }

        $newBusUnit = BusUnit::findOrFail($data['bus_unit_id']);

        if ($newBusUnit->id === $trip->bus_unit_id) {
            return $back;
        }

        $conflict = LandingRoute::busUnitConflict($trip->from, $trip->to, $newBusUnit->id, $trip->day->toDateString(), null, $trip->id);
        if ($conflict) {
            return $back->with('error', "Esa unidad ya está asignada a \"{$conflict->from} → {$conflict->to}\" en un día que se empalma con este viaje.");
        }

        $trip->update([
            'bus_unit_id' => $newBusUnit->id,
            'available_seats' => max(0, $newBusUnit->bookableSeatsCount() - $trip->seatReservations()->count()),
        ]);

        $moved = TripGuide::remapTripReservations($trip->fresh()->load('busUnit'));
        StandingReservation::applyToTrip($trip->fresh());

        $back->with('success', 'Plantilla del viaje actualizada.');

        return $moved
            ? $back->with('warning', 'Estos asientos no existen o ya estaban tomados en la nueva plantilla: '.implode(', ', $moved).' — reasígnalos; mientras tanto no se les envía el boleto.')
            : $back;
    }

    /**
     * Plantilla for ONE date of a guide that has no real trip yet (once
     * it does, changeTripBus() handles it). Stores a per-date override and
     * moves that date's staged apartados to the same-labeled seats; the
     * rest of the guide keeps its own bus.
     */
    public function changeDateBus(Request $request, TripGuide $guide): RedirectResponse
    {
        $data = $request->validate([
            'travel_date' => ['required', 'date', 'after_or_equal:'.$guide->date_from->toDateString(), 'before_or_equal:'.$guide->date_to->toDateString()],
            'bus_unit_id' => ['required', 'exists:bus_units,id'],
        ]);

        $date = Carbon::parse($data['travel_date'])->startOfDay();
        $back = redirect()->route('admin.guias.show', [$guide, 'fecha' => $date->toDateString()]);

        if ($this->matchingTrip($guide, $date)) {
            return $back->with('error', 'Esta fecha ya tiene un viaje abierto — cambia la plantilla desde el viaje.');
        }

        if ($date->lt(Carbon::today())) {
            return $back->with('error', 'Fecha pasada — solo lectura.');
        }

        $newBusUnit = BusUnit::findOrFail($data['bus_unit_id']);

        if ($newBusUnit->id === $guide->busUnitIdFor($date)) {
            return $back;
        }

        $unmatched = DB::transaction(function () use ($guide, $date, $newBusUnit) {
            $unmatched = $guide->remapSeatsTo($newBusUnit, $date);

            // Back to the guide's own bus: drop the override instead of storing a no-op.
            $table = DB::table('trip_guide_date_units')->where('trip_guide_id', $guide->id)->whereDate('date', $date->toDateString());
            if ($newBusUnit->id === $guide->bus_unit_id) {
                $table->delete();
            } else {
                DB::table('trip_guide_date_units')->updateOrInsert(
                    ['trip_guide_id' => $guide->id, 'date' => $date->toDateString()],
                    ['bus_unit_id' => $newBusUnit->id, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            return $unmatched;
        });

        $back->with('success', 'Plantilla del '.$date->format('d/m/Y').' actualizada.');

        return $unmatched->isNotEmpty()
            ? $back->with('warning', 'No se pudieron ligar al mismo asiento en el otro autobús: '.$unmatched->implode(', ').'. Reasígnalos; mientras tanto no se les envía el boleto.')
            : $back;
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

        // Default to today when it's in range — show() itself now
        // handles "today already has a real trip" by rendering that
        // trip's own screen rather than redirecting away, so there's no
        // reason to skip ahead to the next unresolved date anymore.
        $today = now()->startOfDay();

        return $guide->coversDate($today) ? $today : $guide->date_from->copy()->startOfDay();
    }

    private function matchingTrip(TripGuide $guide, Carbon $date): ?LandingRoute
    {
        // withTrashed(): an ended trip is soft-deleted by trips:prune-
        // expired (see LandingRoute::pruneExpired()) so it drops off the
        // bookable lists, but its reservations are untouched and still
        // need to be consultable here — hasEnded() is what blocks writes.
        return LandingRoute::withTrashed()
            ->where('from', $guide->from)
            ->where('to', $guide->to)
            ->whereDate('day', $date->toDateString())
            // Not filtered by bus: the trip's "plantilla" can be switched
            // after the guide was staged (linkTrip() already relinks the
            // reservations), and the guide must still find its trip.
            // Same bus wins if two trips share the route+day.
            ->orderByRaw('bus_unit_id = ? desc', [$guide->bus_unit_id])
            ->first();
    }

    /**
     * A date is "done" for this guide once a real trip exists for it AND
     * there's nothing still pending — specifically, no pending RETURN
     * legs (source_reservation_id set), which wait on a trip going the
     * opposite direction and are unaffected by this guide's own
     * direction having a trip.
     */
    /**
     * Pushes this guide's still-pending apartados for a date onto the
     * already-open trip (same as what happens when a trip is created).
     */
    public function syncTrip(Request $request, TripGuide $guide): RedirectResponse
    {
        $date = Carbon::parse($request->validate(['travel_date' => ['required', 'date']])['travel_date'])->startOfDay();
        $trip = $this->matchingTrip($guide, $date);

        if (! $trip) {
            return back()->with('error', 'No hay un viaje abierto para esa fecha todavía.');
        }

        $result = TripGuide::linkTrip($trip);
        $msg = "Sincronizados {$result['linked']} apartado(s) con el viaje.";
        if ($result['unmatched']) {
            $msg .= ' Asientos sin coincidir: '.implode(', ', $result['unmatched']).'.';
        }

        return back()->with($result['unmatched'] ? 'error' : 'success', $msg);
    }
}
