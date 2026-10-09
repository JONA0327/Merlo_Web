<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\Destination;
use App\Models\StandingReservation;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "De planta" — seats permanently assigned to the same person on a
 * given route, auto-booked every time a matching trip opens (see
 * StandingReservation::applyToTrip()) instead of the admin re-apartando
 * them by hand each time.
 */
class AdminStandingReservationController extends Controller
{
    public function index(): View
    {
        $assignments = StandingReservation::with(['busUnit', 'seat'])->orderBy('from')->orderBy('to')->get();

        // Same person/route/unidad usually means several seats registered
        // one at a time (one per form submit) — grouped here so they show
        // as ONE row instead of a repeated line per seat.
        $groups = $assignments
            ->groupBy(fn (StandingReservation $a) => $a->from.'|'.$a->to.'|'.$a->bus_unit_id.'|'.$a->customer_phone)
            ->map(function ($group) {
                $first = $group->first();

                return (object) [
                    'from' => $first->from,
                    'to' => $first->to,
                    'busUnit' => $first->busUnit,
                    'customer_name' => $first->customer_name,
                    'customer_phone' => $first->customer_phone,
                    'customer_email' => $first->customer_email,
                    'trip_type' => $first->trip_type,
                    'notes' => $first->notes,
                    'is_active' => $group->every(fn (StandingReservation $a) => $a->is_active),
                    'ids' => $group->pluck('id'),
                    'seats' => $group->pluck('seat.label')->filter()->sort(SORT_NATURAL)->values(),
                ];
            })
            ->values();

        return view('admin.planta.index', [
            'groups' => $groups,
            'busUnits' => BusUnit::where('is_active', true)->with('seats')->orderBy('name')->get(),
            'destinations' => Destination::query()->active()->orderBy('name')->get(),
            'tripTypeLabels' => TripTicketPrice::tripTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        StandingReservation::create($data);

        return redirect()->route('admin.planta.index')->with('success', 'Asiento de planta agregado. Se asignará solo cada vez que se abra un viaje de esa ruta.');
    }

    public function update(Request $request, StandingReservation $standing): RedirectResponse
    {
        // The seat/ruta/unidad stay fixed once created — editing here is
        // just for correcting the person's data or pausing it. To
        // reassign the physical seat, delete and create a new one.
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $standing->update($data);

        return redirect()->route('admin.planta.index')->with('success', 'Asiento de planta actualizado.');
    }

    public function destroy(StandingReservation $standing): RedirectResponse
    {
        $standing->delete();

        return redirect()->route('admin.planta.index')->with('success', 'Asiento de planta eliminado. Ya no se asignará automáticamente.');
    }

    /**
     * Edits every seat in a group (same person/route/unidad, see index())
     * at once — the list shows them as one row, so "Editar" acts on all
     * of them together instead of one at a time.
     */
    public function updateMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:standing_reservations,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        StandingReservation::whereIn('id', $data['ids'])->update([
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'trip_type' => $data['trip_type'],
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.planta.index')->with('success', 'Asientos de planta actualizados.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:standing_reservations,id'],
        ]);

        $count = StandingReservation::whereIn('id', $data['ids'])->delete();

        return redirect()->route('admin.planta.index')->with('success', "Eliminados {$count} asiento".($count === 1 ? '' : 's').' de planta.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'bus_unit_id' => ['required', 'exists:bus_units,id'],
            'bus_unit_seat_id' => [
                'required',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $request->input('bus_unit_id')),
                Rule::unique('standing_reservations')->where(fn ($q) => $q
                    ->where('from', $request->input('from'))
                    ->where('to', $request->input('to'))
                    ->where('bus_unit_id', $request->input('bus_unit_id'))),
            ],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,especial,regreso'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'bus_unit_seat_id' => 'asiento',
        ]) + ['is_active' => true];
    }
}
