<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\Destination;
use App\Models\LandingRoute;
use App\Models\Setting;
use App\Models\StandingReservation;
use App\Models\TripGuide;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminLandingRouteController extends Controller
{
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Passed to the create form's <select> so the operator doesn't
        // re-type city names by hand.
        $destinations = Destination::query()->active()->orderBy('name')->get();

        return view('admin.landing-routes', [
            'routes' => $routes,
            'destinations' => $destinations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            // 'schedule_mode' picks which of the two mutually-exclusive
            // date models this trip uses — 'date' (fecha específica, the
            // original single-day trip) stays the default so every
            // existing form submission (no such field) behaves exactly
            // as before. 'weekday' is the new "corre todos los Dom/Lun/…"
            // mode — see scheduleFieldsFrom().
            'schedule_mode' => ['nullable', 'in:date,weekday'],
            'day' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'recurring_weekdays' => ['required_if:schedule_mode,weekday', 'nullable', 'array', 'min:1'],
            'recurring_weekdays.*' => ['integer', 'between:0,6'],
            // 'numeric', not 'integer': the hour/minute <select>s always
            // submit zero-padded strings ("00".."23" / "00".."55"), and
            // PHP's FILTER_VALIDATE_INT (what the integer rule uses)
            // rejects leading-zero strings like "09" as invalid — every
            // hour 00-09 failed validation with 'integer'.
            'departure_time_hour' => ['nullable', 'numeric', 'between:0,23'],
            'departure_time_minute' => ['nullable', 'numeric', 'between:0,59'],
            'available_seats' => ['nullable', 'integer', 'min:0'],
            'bus_unit_id' => ['nullable', 'exists:bus_units,id'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        // A trip with a seat map doesn't get to have a made-up seat count —
        // it's however many bookable seats that unit's layout actually has.
        // The "Asientos disponibles" field is read-only client-side for this
        // reason, but the real guarantee is here, not in the browser.
        if (! empty($validated['bus_unit_id'])) {
            $validated['available_seats'] = BusUnit::find($validated['bus_unit_id'])->bookableSeatsCount();
        }

        $schedule = $this->scheduleFieldsFrom($validated, fn () => now()->toDateString());

        $route = LandingRoute::create([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'day' => $schedule['day'],
            'recurring_weekdays' => $schedule['recurring_weekdays'],
            'return_date' => $schedule['return_date'],
            'departure_time' => $this->departureTimeFrom($validated),
            'available_seats' => $validated['available_seats'] ?? 1,
            'bus_unit_id' => $validated['bus_unit_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'featured' => $validated['featured'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'image' => $request->hasFile('image') ? $request->file('image')->store('landing-routes', 'public') : null,
        ]);

        // Seed every type with the global default so the new trip shows up
        // in /admin/precios with a row for each ticket type right away.
        // If no defaults are configured yet, no rows are created — the
        // LandingRoute::priceFor fallback will surface "—" in the UI
        // until the admin sets a default in /admin/precios.
        $this->seedDefaultPrices($route);

        $linkResult = TripGuide::linkTrip($route);
        $linked = $linkResult['linked'];
        $message = 'Ruta agregada correctamente.';
        if ($linked > 0) {
            $message .= " Se vincularon {$linked} asiento".($linked === 1 ? '' : 's').' que ya estaban apartados desde una guía.';
        }
        if (! empty($linkResult['unmatched'])) {
            $message .= ' ⚠ No se pudo asignar asiento automáticamente a: '.implode(', ', $linkResult['unmatched']).' (la guía estaba en otra plantilla) — reasígnalos desde Apartar asientos.';
        }
        $planta = StandingReservation::applyToTrip($route);
        if ($planta > 0) {
            $message .= " Se asignaron {$planta} asiento".($planta === 1 ? '' : 's').' de planta automáticamente.';
        }
        $defaults = Setting::current()->defaultPrices();
        if (! empty($defaults)) {
            $message .= ' Se copiaron los precios por defecto (modifícalos en "Precios de boleto" si este viaje necesita otros).';
        }

        if ($route->isRecurring()) {
            $guide = TripGuide::ensureForRecurringTrip($route);
            if ($guide?->wasRecentlyCreated) {
                $message .= ' Se creó su guía automática — ya puedes apartar asientos por adelantado para este viaje.';
            }
        }

        return redirect()->route('admin.viajes')->with('success', $message);
    }

    public function edit(LandingRoute $landingRoute): View
    {
        return view('admin.viajes-edit', [
            'route' => $landingRoute,
            'busUnits' => BusUnit::where('is_active', true)
                ->withCount(['seats as bookable_seats_count' => fn ($query) => $query->bookable()])
                ->orderBy('name')
                ->get(),
            // Active destinations for the from/to <select>. If the trip
            // already has a free-text from/to that no longer matches any
            // destination, the blade shows it as a disabled legacy option
            // so the value isn't lost on re-save.
            'destinations' => Destination::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'schedule_mode' => ['nullable', 'in:date,weekday'],
            'day' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'recurring_weekdays' => ['required_if:schedule_mode,weekday', 'nullable', 'array', 'min:1'],
            'recurring_weekdays.*' => ['integer', 'between:0,6'],
            'departure_time_hour' => ['nullable', 'numeric', 'between:0,23'],
            'departure_time_minute' => ['nullable', 'numeric', 'between:0,59'],
            'available_seats' => ['nullable', 'integer', 'min:0'],
            'bus_unit_id' => ['nullable', 'exists:bus_units,id'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
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

        $image = $landingRoute->image;
        if ($request->hasFile('image')) {
            if ($image) {
                Storage::disk('public')->delete($image);
            }
            $image = $request->file('image')->store('landing-routes', 'public');
        } elseif (! empty($validated['remove_image'])) {
            if ($image) {
                Storage::disk('public')->delete($image);
            }
            $image = null;
        }

        $busChanged = (int) ($validated['bus_unit_id'] ?? 0) !== (int) $landingRoute->bus_unit_id;

        $schedule = $this->scheduleFieldsFrom(
            $validated,
            fn () => $landingRoute->day,
            $landingRoute->isRecurring() ? 'weekday' : 'date'
        );

        $landingRoute->update([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'day' => $schedule['day'],
            'recurring_weekdays' => $schedule['recurring_weekdays'],
            'return_date' => $schedule['return_date'],
            'departure_time' => $this->departureTimeFrom($validated, $landingRoute->departure_time),
            'available_seats' => $validated['available_seats'] ?? $landingRoute->available_seats,
            'bus_unit_id' => $validated['bus_unit_id'] ?? null,
            'is_active' => $validated['is_active'] ?? $landingRoute->is_active,
            'featured' => $validated['featured'] ?? $landingRoute->featured,
            'sort_order' => $validated['sort_order'] ?? $landingRoute->sort_order,
            'image' => $image,
        ]);

        $freshRoute = $landingRoute->fresh();
        $message = 'Viaje actualizado correctamente.';
        // Plantilla changed: existing apartados follow their seat label to
        // the new bus before anything gets flagged as "no coincide".
        if ($busChanged && $freshRoute->bus_unit_id) {
            $moved = TripGuide::remapTripReservations($freshRoute->load('busUnit'));
            if ($moved) {
                $message .= ' ⚠ Estos asientos no existen o ya estaban tomados en la nueva plantilla: '.implode(', ', $moved).' — reasígnalos desde Apartar asientos.';
            }
        }
        $linkResult = TripGuide::linkTrip($freshRoute);
        $linked = $linkResult['linked'];
        if ($linked > 0) {
            $message .= " Se vincularon {$linked} asiento".($linked === 1 ? '' : 's').' que ya estaban apartados desde una guía.';
        }
        if (! empty($linkResult['unmatched'])) {
            $message .= ' ⚠ No se pudo asignar asiento automáticamente a: '.implode(', ', $linkResult['unmatched']).' (la guía estaba en otra plantilla) — reasígnalos desde Apartar asientos.';
        }
        $planta = StandingReservation::applyToTrip($freshRoute);
        if ($planta > 0) {
            $message .= " Se asignaron {$planta} asiento".($planta === 1 ? '' : 's').' de planta automáticamente.';
        }

        if ($freshRoute->isRecurring()) {
            $guide = TripGuide::ensureForRecurringTrip($freshRoute);
            if ($guide?->wasRecentlyCreated) {
                $message .= ' Se creó su guía automática — ya puedes apartar asientos por adelantado para este viaje.';
            }
        }

        return redirect()->route('admin.viajes')->with('success', $message);
    }

    /**
     * Rebuilds the 24h "HH:MM" string from the two selects in the form.
     * Both selects always submit, so a missing hour/minute means the field
     * was left untouched — keep the existing value in that case.
     *
     * @param  array<string, mixed>  $validated
     */
    private function departureTimeFrom(array $validated, ?string $fallback = '00:00'): string
    {
        $hour = $validated['departure_time_hour'] ?? null;
        $minute = $validated['departure_time_minute'] ?? null;

        if ($hour === null || $minute === null) {
            return $fallback ?? '00:00';
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * Resolves day / recurring_weekdays / return_date from the
     * schedule_mode toggle — 'date' (fecha específica, the original
     * single-day trip, and the default when the field is missing so old
     * form submissions keep behaving exactly as before) or 'weekday'
     * (the new "corre todos los Dom/Lun/…" mode, no fecha). The two
     * modes are mutually exclusive — switching one way clears the
     * other's fields rather than leaving stale data behind.
     *
     * $defaultDay supplies 'day' when schedule_mode is 'date' but no day
     * was submitted — store() defaults to today, update() keeps the
     * trip's existing day, same as before this feature existed.
     * $currentMode is only used when schedule_mode itself is missing
     * from the request (e.g. a stale cached form) — update() passes the
     * trip's own current mode so it isn't silently flipped back to
     * 'date'; store() has no existing trip to infer from, so it just
     * defaults to 'date'.
     *
     * @param  array<string, mixed>  $validated
     * @return array{day: mixed, recurring_weekdays: ?array<int>, return_date: ?string}
     */
    private function scheduleFieldsFrom(array $validated, \Closure $defaultDay, string $currentMode = 'date'): array
    {
        $mode = $validated['schedule_mode'] ?? $currentMode;

        if ($mode === 'weekday') {
            return [
                'day' => null,
                'recurring_weekdays' => array_values(array_unique(array_map('intval', $validated['recurring_weekdays'] ?? []))),
                'return_date' => null,
            ];
        }

        return [
            'day' => $validated['day'] ?? $defaultDay(),
            'recurring_weekdays' => null,
            'return_date' => $validated['return_date'] ?? null,
        ];
    }

    /**
     * Copy the global default prices onto this trip as active overrides —
     * the admin can still edit any cell in /admin/precios if this trip
     * needs a special price. Skipped types (no default set yet) just
     * don't get a row, and priceFor() will surface the missing value
     * consistently with the rest of the UI.
     */
    private function seedDefaultPrices(LandingRoute $route): void
    {
        $defaults = Setting::current()->defaultPrices();

        foreach ($defaults as $type => $price) {
            // Never overwrite an admin-set override on this trip
            // (defensive — store() runs on a freshly created trip so
            // there shouldn't be any rows, but this future-proofs the
            // helper if we ever call it elsewhere).
            $existing = $route->prices()->where('trip_type', $type)->first();
            if ($existing) {
                continue;
            }

            $route->prices()->create([
                'trip_type' => $type,
                'price' => $price,
                'is_active' => true,
            ]);
        }
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
        if ($landingRoute->image) {
            Storage::disk('public')->delete($landingRoute->image);
        }

        $landingRoute->delete();

        return redirect()->route('admin.viajes')->with('success', 'Viaje eliminado correctamente.');
    }
}
