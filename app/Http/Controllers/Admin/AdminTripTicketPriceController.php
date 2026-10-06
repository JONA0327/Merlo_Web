<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingRoute;
use App\Models\Setting;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTripTicketPriceController extends Controller
{
    /**
     * The "Precios de boleto" screen:
     *
     *   1. Top: "Precios generales" — one set of defaults (Solo ida /
     *      Redondo / Especial / Regreso) that every new trip copies
     *      when it's created. Stored on the singleton settings row
     *      so the admin doesn't need a separate CRUD for "global config".
     *
     *   2. Bottom: per-trip overrides — the existing table where each
     *      cell is either an override (admin set a special price for
     *      this trip) or empty (inherits the default). "Reset" links
     *      next to each cell drop the override row so the trip falls
     *      back to the default again.
     */
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->with(['prices' => fn ($q) => $q->whereIn('trip_type', array_keys(TripTicketPrice::tripTypes()))])
            ->orderBy('is_active', 'desc')
            ->orderBy('day')
            ->orderBy('departure_time')
            ->get();

        $setting = Setting::current();

        return view('admin.precios.index', [
            'routes' => $routes,
            'tripTypes' => TripTicketPrice::tripTypes(),
            'defaults' => [
                TripTicketPrice::TYPE_ONE_WAY => $setting->defaultPriceFor(TripTicketPrice::TYPE_ONE_WAY),
                TripTicketPrice::TYPE_ROUND_TRIP => $setting->defaultPriceFor(TripTicketPrice::TYPE_ROUND_TRIP),
                TripTicketPrice::TYPE_ESPECIAL => $setting->defaultPriceFor(TripTicketPrice::TYPE_ESPECIAL),
                TripTicketPrice::TYPE_REGRESO => $setting->defaultPriceFor(TripTicketPrice::TYPE_REGRESO),
            ],
        ]);
    }

    /**
     * Persist the global defaults AND the (route × trip_type) overrides
     * in one shot. Accepts payloads shaped like:
     *   defaults[one_way]              = 650
     *   defaults[round_trip]           = 1100
     *   prices[123][one_way]           = 700     (per-trip override)
     *   prices[123][round_trip]        = 1150
     *   prices[123][is_active][one_way]   = 1
     *   prices[123][is_active][round_trip] = 1
     *   prices[123][reset][one_way]    = 1     (delete the override row)
     *
     * An empty `prices[routeId][type]` cell (or `reset=1`) clears the
     * override and lets the trip fall back to the default; otherwise
     * the value (or 0/inactive when the cell is blank but `is_active=1`
     * isn't set) becomes a stored override on that specific trip.
     */
    public function update(Request $request): RedirectResponse
    {
        $defaults = $request->input('defaults', []);
        $payload = $request->input('prices', []);

        $this->saveDefaults($defaults);

        foreach ($payload as $routeId => $perType) {
            $route = LandingRoute::find($routeId);
            if (! $route) continue;

            foreach (TripTicketPrice::tripTypes() as $type => $label) {
                $raw = $perType[$type] ?? null;
                $isActive = isset($perType['is_active'][$type]) && (int) $perType['is_active'][$type] === 1;
                $wantsReset = isset($perType['reset'][$type]) && (int) $perType['reset'][$type] === 1;

                $existing = $route->prices()->where('trip_type', $type)->first();

                // Explicit reset → drop the row so the trip inherits the default.
                // Done before value parsing so reset=true on a non-empty cell
                // still means "delete, not update".
                if ($wantsReset) {
                    if ($existing) {
                        $existing->delete();
                    }
                    continue;
                }

                $numeric = null;
                if ($raw !== null && $raw !== '') {
                    $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $raw);
                    if ($numeric < 0) $numeric = 0;
                }

                if ($numeric === null || $numeric <= 0) {
                    // Empty cell with no reset flag → preserve "we don't sell
                    // this type here" intent by marking the row inactive
                    // rather than silently switching the trip back to the
                    // default. The admin uses the "Restablecer" checkbox
                    // when they actually want the fallback.
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

    /**
     * Validate + persist the global default prices. Blank / negative
     * values are stored as null (meaning "no default yet — trips won't
     * fall back to a number") so the UI consistently shows "—" for
     * that type everywhere it's read.
     */
    private function saveDefaults(array $payload): void
    {
        $setting = Setting::current();

        $columnFor = [
            TripTicketPrice::TYPE_ONE_WAY => 'default_price_one_way',
            TripTicketPrice::TYPE_ROUND_TRIP => 'default_price_round_trip',
            TripTicketPrice::TYPE_ESPECIAL => 'default_price_especial',
            TripTicketPrice::TYPE_REGRESO => 'default_price_regreso',
        ];

        $update = [];
        foreach ($columnFor as $type => $column) {
            $raw = $payload[$type] ?? null;
            $numeric = null;
            if ($raw !== null && $raw !== '') {
                $candidate = (float) preg_replace('/[^0-9.]/', '', (string) $raw);
                if ($candidate > 0) {
                    $numeric = $candidate;
                }
            }
            $update[$column] = $numeric;
        }

        $setting->update($update);
    }
}
