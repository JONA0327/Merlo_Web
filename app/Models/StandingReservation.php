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
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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
     */
    public static function activeSeatIdsFor(string $from, string $to, int $busUnitId, ?int $excludeSkippedForTripId = null): Collection
    {
        $query = static::query()
            ->where('from', $from)
            ->where('to', $to)
            ->where('bus_unit_id', $busUnitId)
            ->where('is_active', true);

        if ($excludeSkippedForTripId !== null) {
            $query->whereDoesntHave('skips', fn ($q) => $q->where('landing_route_id', $excludeSkippedForTripId));
        }

        return $query->pluck('bus_unit_seat_id');
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

        $assignments = static::query()
            ->where('from', $trip->from)
            ->where('to', $trip->to)
            ->where('bus_unit_id', $trip->bus_unit_id)
            ->where('is_active', true)
            ->whereDoesntHave('skips', fn ($q) => $q->where('landing_route_id', $trip->id))
            ->get();

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
                'unit_price' => (float) ($trip->priceFor($assignment->trip_type)?->price ?? 0),
                'customer_name' => $assignment->customer_name,
                'customer_email' => $assignment->customer_email,
                'customer_phone' => $assignment->customer_phone,
                'status' => SeatReservation::STATUS_PENDING,
                'payment_status' => SeatReservation::PAYMENT_PENDING,
                'notes' => 'Asiento de planta'.($assignment->notes ? ' — '.$assignment->notes : '').' (#'.$assignment->id.')',
            ]);

            \App\Jobs\SendLinkedTicketNotification::dispatch($reservation->id);

            $takenIds[] = $assignment->bus_unit_seat_id;
            $applied++;
        }

        return $applied;
    }
}
