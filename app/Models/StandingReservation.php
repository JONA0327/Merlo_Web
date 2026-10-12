<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * "De planta" — a seat permanently assigned to the same person on a
 * given route (any bus running that from/to/bus_unit combination),
 * so the admin doesn't have to re-register them every time a new trip
 * opens. applyToTrip() is called right alongside TripGuide::linkTrip()
 * whenever a trip is created.
 */
class StandingReservation extends Model
{
    protected $fillable = [
        'from',
        'to',
        'bus_unit_id',
        'bus_unit_seat_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'trip_type',
        'weekdays',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weekdays' => 'array',
    ];

    /** Sunday-first, same convention as LandingRoute::recurring_weekdays. */
    private const WEEKDAY_LABELS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    /**
     * Whether this assignment is "de planta" on this specific weekday —
     * empty/null weekdays means every day (the original behavior, kept
     * as the default so nothing already in the database changes meaning).
     */
    public function appliesOnWeekday(int $dayOfWeek): bool
    {
        return empty($this->weekdays) || in_array($dayOfWeek, $this->weekdays, true);
    }

    public function weekdaysLabel(): string
    {
        if (empty($this->weekdays)) {
            return 'Todos los días';
        }

        return collect($this->weekdays)->sort()->map(fn ($day) => self::WEEKDAY_LABELS[$day] ?? '?')->implode(', ');
    }

    /**
     * Marks a seat "de planta" for ONE weekday — merged into whatever
     * days are already set for this exact seat, not replaced, so
     * checking "Asiento predeterminado" again for a different day the
     * same passenger travels (e.g. later on Thursdays too) accumulates
     * instead of silently dropping Monday. Called from the "mark_as_
     * standing" checkbox in both Apartar asientos and Guía.
     *
     * @param array{from: string, to: string, bus_unit_id: int, bus_unit_seat_id: int} $keys
     * @param array<string, mixed> $attributes customer_name/customer_phone/customer_email/trip_type/notes
     */
    public static function markStanding(array $keys, array $attributes, int $weekday): self
    {
        $standing = static::firstOrNew($keys);
        $weekdays = array_values(array_unique([...($standing->weekdays ?? []), $weekday]));

        $standing->fill($attributes + ['weekdays' => $weekdays, 'is_active' => true]);
        $standing->save();

        return $standing;
    }

    /**
     * Pre-stages a real apartado in $guide for every day from today
     * through the guide's own date_to that an active standing
     * assignment for this route/bus applies to — without this, "de
     * planta" was just a purple color blocking the seat on the picker
     * for a future date, with no actual apartado behind it (nothing
     * for the day-before reminder to find, nothing in "Apartados
     * pendientes"). A date a real trip already covers is left alone —
     * applyToTrip() already staged (or will stage) it there, and
     * double-staging here would double-book the seat. Safe to call
     * repeatedly (checks what's already there before creating
     * anything) — called wherever a guide's window gets touched:
     * AdminTripGuideController::index(), the daily
     * guides:provision-recurring command, and right after a standing
     * assignment is first created so it shows up immediately.
     *
     * @return int number of apartados created
     */
    public static function provisionGuideReservations(TripGuide $guide): int
    {
        $assignments = static::query()
            ->where('from', $guide->from)
            ->where('to', $guide->to)
            ->where('bus_unit_id', $guide->bus_unit_id)
            ->where('is_active', true)
            // "De planta" regreso needs its own leg-matching against an
            // actual outbound ticket — out of scope for this forward-leg
            // provisioning, same as applyToTrip() doesn't special-case it.
            ->where('trip_type', '!=', TripTicketPrice::TYPE_REGRESO)
            ->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        $today = now()->startOfDay();
        $start = $guide->date_from->gt($today) ? $guide->date_from->copy() : $today->copy();
        $applied = 0;

        for ($date = $start; $date->lte($guide->date_to); $date = $date->copy()->addDay()) {
            $dayAssignments = $assignments->filter(fn (self $a) => $a->appliesOnWeekday($date->dayOfWeek));
            if ($dayAssignments->isEmpty()) {
                continue;
            }

            $hasRealTrip = LandingRoute::query()
                ->where('from', $guide->from)
                ->where('to', $guide->to)
                ->where('bus_unit_id', $guide->bus_unit_id)
                ->whereDate('day', $date->toDateString())
                ->exists();
            if ($hasRealTrip) {
                continue;
            }

            $takenSeatIds = SeatReservation::whereNull('landing_route_id')
                ->whereNotNull('trip_guide_id')
                ->whereDate('travel_date', $date->toDateString())
                ->where('leg', SeatReservation::LEG_OUTBOUND)
                ->pluck('bus_unit_seat_id')
                ->all();

            // Each standing seat stages as its own separate apartado —
            // not bundled under one group card — so "Borrar" on one
            // doesn't take the others with it, same as any other
            // independently-managed standing assignment.
            foreach ($dayAssignments as $assignment) {
                if (in_array($assignment->bus_unit_seat_id, $takenSeatIds, true)) {
                    continue;
                }

                // No immediate WhatsApp notice here, unlike a manually
                // staged apartado — these are created well ahead of time
                // in a batch; SendDayBeforeReminders is what actually
                // notifies, exactly one day before each one travels.
                SeatReservation::create([
                    'trip_guide_id' => $guide->id,
                    'travel_date' => $date->toDateString(),
                    'bus_unit_seat_id' => $assignment->bus_unit_seat_id,
                    'trip_type' => $assignment->trip_type,
                    'leg' => SeatReservation::LEG_OUTBOUND,
                    'unit_price' => TripTicketPrice::estimateUnitPrice($guide->from, $guide->to, $guide->bus_unit_id, $assignment->trip_type),
                    'customer_name' => $assignment->customer_name,
                    'customer_email' => $assignment->customer_email,
                    'customer_phone' => $assignment->customer_phone,
                    'status' => SeatReservation::STATUS_PENDING,
                    'payment_status' => SeatReservation::PAYMENT_PENDING,
                    'notes' => 'Asiento de planta'.($assignment->notes ? ' — '.$assignment->notes : ''),
                ]);

                $takenSeatIds[] = $assignment->bus_unit_seat_id;
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * provisionGuideReservations(), but looked up by route instead of an
     * already-known guide — for right after a standing assignment is
     * created, when there may or may not be a guide for its route yet.
     * A no-op when there isn't one; it'll get provisioned the next time
     * one is (ensureForRecurringTrip()/the daily command already call
     * provisionGuideReservations() for every guide, recurring or not).
     */
    public static function provisionForRoute(string $from, string $to, int $busUnitId): int
    {
        $guide = TripGuide::where('from', $from)->where('to', $to)->where('bus_unit_id', $busUnitId)->first();

        return $guide ? static::provisionGuideReservations($guide) : 0;
    }

    public function busUnit(): BelongsTo
    {
        return $this->belongsTo(BusUnit::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(BusUnitSeat::class, 'bus_unit_seat_id');
    }

    public function skips(): HasMany
    {
        return $this->hasMany(StandingReservationSkip::class);
    }

    /**
     * bus_unit_seat_id of every active standing assignment for this
     * route/bus — used to paint the seat picker purple in both Apartar
     * asientos and Guía. When $excludeSkippedForTripId is given (a real
     * trip's id), an assignment the admin explicitly released for THAT
     * trip (see releaseStanding()) is left out, so a "no viaja hoy" seat
     * goes back to looking and behaving like any other free seat for
     * this one trip — every other trip still gets it automatically.
     * $weekday (Carbon's own dayOfWeek, 0=Dom…6=Sáb) additionally scopes
     * to assignments that actually apply on that specific day — "de
     * planta" is per-weekday, not "every run of this route" — see
     * appliesOnWeekday(). Omit it to ignore the day (every assignment).
     */
    public static function activeSeatIdsFor(string $from, string $to, int $busUnitId, ?int $excludeSkippedForTripId = null, ?int $weekday = null): Collection
    {
        $query = static::query()
            ->where('from', $from)
            ->where('to', $to)
            ->where('bus_unit_id', $busUnitId)
            ->where('is_active', true);

        if ($excludeSkippedForTripId !== null) {
            $query->whereDoesntHave('skips', fn ($q) => $q->where('landing_route_id', $excludeSkippedForTripId));
        }

        $assignments = $query->get(['id', 'bus_unit_seat_id', 'weekdays']);

        if ($weekday !== null) {
            $assignments = $assignments->filter(fn (self $a) => $a->appliesOnWeekday($weekday));
        }

        return $assignments->pluck('bus_unit_seat_id');
    }

    /**
     * Carries every active standing assignment for this route (from/to)
     * over from an OLD bus unit to a NEW one, matched by seat LABEL —
     * called right alongside TripGuide::remapSeatsTo() when an admin
     * changes a guide's "plantilla": a "de planta" seat belongs to the
     * PERSON on that ROUTE, not to whichever specific bus happens to be
     * running it, so it has to follow the same way an already-staged
     * apartado does. Skipped (left pointing at the old bus, same as an
     * unmatched apartado) when the label doesn't exist on the new bus, or
     * another standing assignment already claims that exact slot there.
     *
     * @return Collection<int, string> labels that couldn't be carried over
     */
    public static function remapToBusUnit(string $from, string $to, int $oldBusUnitId, BusUnit $newBusUnit): Collection
    {
        $assignments = static::query()
            ->where('from', $from)
            ->where('to', $to)
            ->where('bus_unit_id', $oldBusUnitId)
            ->with('seat')
            ->get();

        $newSeatsByLabel = $newBusUnit->seats()->get()->keyBy('label');
        $unmatched = collect();

        foreach ($assignments as $assignment) {
            $label = $assignment->seat?->label;
            $newSeat = $label !== null ? $newSeatsByLabel->get($label) : null;

            if (! $newSeat) {
                $unmatched->push($label ?? "#{$assignment->bus_unit_seat_id}");

                continue;
            }

            $taken = static::where('from', $from)
                ->where('to', $to)
                ->where('bus_unit_id', $newBusUnit->id)
                ->where('bus_unit_seat_id', $newSeat->id)
                ->exists();

            if ($taken) {
                $unmatched->push($label);

                continue;
            }

            $assignment->update([
                'bus_unit_id' => $newBusUnit->id,
                'bus_unit_seat_id' => $newSeat->id,
            ]);
        }

        return $unmatched->unique()->values();
    }

    /**
     * Auto-books every active standing assignment that matches this
     * trip's route/bus — skipping any whose seat is already taken by
     * someone else on it (a real customer's own apartado always wins).
     * Stays payment-pending like any other apartado; queues the same
     * "trip is open" notification as a linked guía reservation (see
     * SendLinkedTicketNotification) — paid gets the ticket, unpaid gets
     * the reservation notice.
     *
     * @return int number of seats auto-booked
     */
    public static function applyToTrip(LandingRoute $trip): int
    {
        if ($trip->day === null || $trip->bus_unit_id === null) {
            return 0;
        }

        // "De planta" is per-weekday ("todos los lunes"), not "every run
        // of this route" — only assignments whose own weekdays (or no
        // restriction at all) cover this trip's actual day apply here.
        $assignments = static::query()
            ->where('from', $trip->from)
            ->where('to', $trip->to)
            ->where('bus_unit_id', $trip->bus_unit_id)
            ->where('is_active', true)
            ->whereDoesntHave('skips', fn ($q) => $q->where('landing_route_id', $trip->id))
            ->get()
            ->filter(fn (self $a) => $a->appliesOnWeekday($trip->day->dayOfWeek));

        if ($assignments->isEmpty()) {
            return 0;
        }

        $takenIds = $trip->seatReservations()->pluck('bus_unit_seat_id')->all();
        $applied = 0;

        foreach ($assignments as $assignment) {
            if (in_array($assignment->bus_unit_seat_id, $takenIds, true)) {
                continue;
            }

            $reservation = SeatReservation::create([
                'landing_route_id' => $trip->id,
                'bus_unit_seat_id' => $assignment->bus_unit_seat_id,
                'trip_type' => $assignment->trip_type,
                'leg' => $assignment->trip_type === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND,
                'unit_price' => $trip->seatPriceFor($assignment->trip_type),
                'customer_name' => $assignment->customer_name,
                'customer_email' => $assignment->customer_email,
                'customer_phone' => $assignment->customer_phone,
                'status' => SeatReservation::STATUS_PENDING,
                'payment_status' => SeatReservation::PAYMENT_PENDING,
                'notes' => 'Asiento de planta'.($assignment->notes ? ' — '.$assignment->notes : ''),
            ]);

            \App\Jobs\SendLinkedTicketNotification::dispatch($reservation->id);

            $takenIds[] = $assignment->bus_unit_seat_id;
            $applied++;
        }

        return $applied;
    }
}
