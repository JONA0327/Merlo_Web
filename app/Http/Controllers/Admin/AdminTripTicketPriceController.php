<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingRoute;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTripTicketPriceController extends Controller
{
    /**
     * The "Precios de boleto" screen: one row per trip, one editable
     * cell per ticket type — every trip prices itself, no shared
     * default. An empty cell means "sin precio configurado", shown as
     * such everywhere the trip's price is read (seat picker, search
     * results, etc.) instead of silently borrowing another trip's price.
     */
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->with(['prices' => fn ($q) => $q->whereIn('trip_type', array_keys(TripTicketPrice::tripTypes()))])
            ->orderBy('is_active', 'desc')
            ->orderBy('day')
            ->orderBy('departure_time')
            ->get();

        return view('admin.precios.index', [
            'routes' => $routes,
            'tripTypes' => TripTicketPrice::tripTypes(),
        ]);
    }

    /**
     * Persists the (route × trip_type) overrides. Accepts payloads shaped like:
     *   prices[123][one_way]              = 700
     *   prices[123][round_trip]           = 1150
     *   prices[123][is_active][one_way]   = 1
     *   prices[123][is_active][round_trip] = 1
     *
     * An empty `prices[routeId][type]` cell clears/deactivates that
     * trip's price for that type — there's no default left to fall back to.
     */
    public function update(Request $request): RedirectResponse
    {
        $payload = $request->input('prices', []);

        foreach ($payload as $routeId => $perType) {
            $route = LandingRoute::find($routeId);
            if (! $route) continue;

            foreach (TripTicketPrice::tripTypes() as $type => $label) {
                $raw = $perType[$type] ?? null;
                $isActive = isset($perType['is_active'][$type]) && (int) $perType['is_active'][$type] === 1;

                $existing = $route->prices()->where('trip_type', $type)->first();

                $numeric = null;
                if ($raw !== null && $raw !== '') {
                    $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $raw);
                    if ($numeric < 0) $numeric = 0;
                }

                if ($numeric === null || $numeric <= 0) {
                    // Empty cell → preserve "we don't sell this type here"
                    // intent by marking the row inactive rather than
                    // deleting it outright.
                    if ($existing) {
                        $existing->update(['is_active' => false, 'price' => 0]);
                    }
                    continue;
                }

                if ($existing) {
                    $existing->update(['price' => $numeric, 'is_active' => $isActive]);
                } else {
                    $route->prices()->create([
                        'trip_type' => $type,
                        'price' => $numeric,
                        'is_active' => $isActive,
                    ]);
                }
            }
        }

        return redirect()
            ->route('admin.precios.index')
            ->with('success', 'Precios de boleto actualizados.');
    }
}
