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
     * The "Precios de boleto" screen: one row per trip, two prices
     * (ida / redondo) editable inline. This is the single source of
     * truth for ticket pricing — the trip form no longer carries
     * a price field, and the seat-picker reads from here at checkout.
     */
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->with(['prices' => fn ($q) => $q->whereIn('trip_type', [
                TripTicketPrice::TYPE_ONE_WAY,
                TripTicketPrice::TYPE_ROUND_TRIP,
            ])])
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
     * Persist the (route × trip_type) prices in one shot. Accepts
     * an array like:
     *   prices[123][one_way]    = 650
     *   prices[123][round_trip] = 1100
     *   prices[123][is_active][one_way]    = 1
     *   prices[123][is_active][round_trip] = 1
     * Rows with an empty price are upserted as inactive so the admin
     * can deliberately turn a type off without losing the row.
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

                // Strip everything except digits and dot so a pasted
                // "$1,200.00" still saves as 1200.00. Empty → null.
                $numeric = null;
                if ($raw !== null && $raw !== '') {
                    $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $raw);
                    if ($numeric < 0) $numeric = 0;
                }

                $existing = $route->prices()->where('trip_type', $type)->first();

                if ($numeric === null || $numeric <= 0) {
                    // Empty input → either delete the row or mark it
                    // inactive. Keeping an inactive row preserves the
                    // intent ("we don't sell this here") in the DB.
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
