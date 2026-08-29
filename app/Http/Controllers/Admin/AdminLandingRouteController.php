<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\LandingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminLandingRouteController extends Controller
{
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.landing-routes', [
            'routes' => $routes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'string', 'max:50'],
            'day' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'departure_time' => ['nullable', 'string', 'max:20'],
            'available_seats' => ['nullable', 'integer', 'min:0'],
            'bus_unit_id' => ['nullable', 'exists:bus_units,id'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // A trip with a seat map doesn't get to have a made-up seat count —
        // it's however many bookable seats that unit's layout actually has.
        // The "Asientos disponibles" field is read-only client-side for this
        // reason, but the real guarantee is here, not in the browser.
        if (! empty($validated['bus_unit_id'])) {
            $validated['available_seats'] = BusUnit::find($validated['bus_unit_id'])->bookableSeatsCount();
        }

        LandingRoute::create([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'duration' => $validated['duration'],
            'day' => $validated['day'] ?? now()->toDateString(),
            'return_date' => $validated['return_date'] ?? null,
            'departure_time' => $validated['departure_time'] ?? '00:00',
            'available_seats' => $validated['available_seats'] ?? 1,
            'bus_unit_id' => $validated['bus_unit_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'featured' => $validated['featured'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.viajes')->with('success', 'Ruta agregada correctamente. Ahora configura el precio desde la sección "Precios de boleto".');
    }

    public function edit(LandingRoute $landingRoute): View
    {
        return view('admin.viajes-edit', [
            'route' => $landingRoute,
            'busUnits' => BusUnit::where('is_active', true)
                ->withCount(['seats as bookable_seats_count' => fn ($query) => $query->bookable()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'string', 'max:50'],
            'day' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'departure_time' => ['nullable', 'string', 'max:20'],
            'available_seats' => ['nullable', 'integer', 'min:0'],
            'bus_unit_id' => ['nullable', 'exists:bus_units,id'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Same rule as store(): a seat-mapped trip's available count is
        // whatever bookable seats remain in that unit's layout, not a
        // number typed into the form — client-side the field is read-only,
        // but this is the actual guarantee.
        if (! empty($validated['bus_unit_id'])) {
            $bookable = BusUnit::find($validated['bus_unit_id'])->bookableSeatsCount();
            $alreadyReserved = $landingRoute->seatReservations()->count();
            $validated['available_seats'] = max(0, $bookable - $alreadyReserved);
        }

        $landingRoute->update([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'duration' => $validated['duration'],
            'day' => $validated['day'] ?? $landingRoute->day,
            'return_date' => $validated['return_date'] ?? null,
            'departure_time' => $validated['departure_time'] ?? $landingRoute->departure_time,
            'available_seats' => $validated['available_seats'] ?? $landingRoute->available_seats,
            'bus_unit_id' => $validated['bus_unit_id'] ?? null,
            'is_active' => $validated['is_active'] ?? $landingRoute->is_active,
            'featured' => $validated['featured'] ?? $landingRoute->featured,
            'sort_order' => $validated['sort_order'] ?? $landingRoute->sort_order,
        ]);

        return redirect()->route('admin.viajes')->with('success', 'Viaje actualizado correctamente.');
    }

    public function toggleFeatured(LandingRoute $landingRoute): RedirectResponse
    {
        $landingRoute->update([
            'featured' => !$landingRoute->featured,
        ]);

        return redirect()->route('admin.viajes')->with('success', $landingRoute->featured ? 'Viaje destacado.' : 'Viaje no destacado.');
    }

    public function destroy(LandingRoute $landingRoute): RedirectResponse
    {
        $landingRoute->delete();

        return redirect()->route('admin.viajes')->with('success', 'Viaje eliminado correctamente.');
    }
}
