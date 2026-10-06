<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\Destination;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripGuide;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        // If a real trip already matching this route/bus/date exists,
        // the guide has already done its job for that day (or was never
        // needed) — send the admin to the normal Apartar screen instead
        // of letting two parallel booking paths fight over the same
        // seats.
        $existingTrip = $this->matchingTrip($guide, $date);
        if ($existingTrip) {
            return redirect()
                ->route('admin.asientos.show', $existingTrip)
                ->with('success', 'Ya existe un viaje abierto para esta fecha — los asientos se apartan ahí directamente.');
        }

        $pendingForDate = $guide->pendingSeatReservations()
            ->whereDate('travel_date', $date->toDateString())
            ->get(['id', 'bus_unit_seat_id', 'status']);

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

        return view('admin.guias.show', [
            'guide' => $guide,
            'date' => $date,
            'pendingReservationsForDate' => $pendingForDate->keyBy('bus_unit_seat_id'),
            'roots' => $roots->sortBy('travel_date')->values(),
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($data['travel_date'])->startOfDay();

        if ($this->matchingTrip($guide, $date)) {
            return back()->withInput()->with('error', 'Ya existe un viaje abierto para esta fecha — usa Apartar asientos directamente ahí.');
        }

        $tripType = $data['trip_type'];

        // A physical seat can't be double-booked for the same date,
        // regardless of which guide staged it — bus_unit_seat_id is the
        // same row either way.
        $alreadyTaken = SeatReservation::whereNull('landing_route_id')
            ->whereNotNull('trip_guide_id')
            ->whereIn('bus_unit_seat_id', $data['seat_ids'])
            ->whereDate('travel_date', $date->toDateString())
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

        DB::transaction(function () use ($guide, $data, $request, $tripType, $date) {
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
                    'reserved_by' => $request->user()?->id,
                    'notes' => $root ? 'group:'.$root->id : ($data['notes'] ?? null),
                ]);
                $root ??= $new;
            }
        });

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
}
