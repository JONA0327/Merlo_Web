<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A "guía" is a staging area for seat reservations made for a route/date
 * range BEFORE the real trip (LandingRoute) exists in the system — some
 * trips aren't "opened" (created here) until the same day or a few days
 * before departure, but customers still call ahead to reserve specific
 * seats. A guide holds those as SeatReservation rows with no
 * landing_route_id yet (just a bus_unit_seat_id + travel_date); once a
 * matching trip is created, linkTrip() attaches them automatically.
 */
class TripGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'from',
        'to',
        'bus_unit_id',
        'date_from',
        'date_to',
        'notes',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function busUnit(): BelongsTo
    {
        return $this->belongsTo(BusUnit::class);
    }

    public function seatReservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    /**
     * Reservations still waiting for a real trip to attach to —
     * everything shown/editable from the guide's own page.
     */
    public function pendingSeatReservations(): HasMany
    {
        return $this->seatReservations()->whereNull('landing_route_id');
    }

    public function coversDate(\DateTimeInterface $date): bool
    {
        return $this->date_from->lte($date) && $this->date_to->gte($date);
    }

    /**
     * Whether a trip qualifies as "the trip this guide was staged for" —
     * same route, same physical bus (so bus_unit_seat_id references line
     * up without remapping), and its day falls inside the guide's window.
     */
    public function matchesTrip(LandingRoute $trip): bool
    {
        return $this->from === $trip->from
            && $this->to === $trip->to
            && $this->bus_unit_id === $trip->bus_unit_id
            && $trip->day !== null
            && $this->coversDate($trip->day);
    }

    /**
     * Called whenever a trip is created/edited (see
     * AdminLandingRouteController) — finds every guide whose route, bus
     * unit and date range match this trip, and attaches every
     * still-pending reservation staged for that exact day. Recomputes
     * unit_price/leg from the now-real trip, same as a normal apartado.
     *
     * @return int number of seats linked
     */
    public static function linkTrip(LandingRoute $trip): int
    {
        if ($trip->day === null || $trip->bus_unit_id === null) {
            return 0;
        }

        $guides = static::query()
            ->where('from', $trip->from)
            ->where('to', $trip->to)
            ->where('bus_unit_id', $trip->bus_unit_id)
            ->where('date_from', '<=', $trip->day)
            ->where('date_to', '>=', $trip->day)
            ->get();

        $linked = 0;

        foreach ($guides as $guide) {
            $reservations = $guide->pendingSeatReservations()
                ->whereDate('travel_date', $trip->day)
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'landing_route_id' => $trip->id,
                    'unit_price' => (float) ($trip->priceFor($reservation->trip_type)?->price ?? 0),
                ]);
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * Re-points every still-pending reservation's bus_unit_seat_id to the
     * seat with the same label on the new bus unit (called right before
     * bus_unit_id is actually changed on the guide). Seats whose label
     * doesn't exist on the new unit are left pointing at the old seat and
     * returned so the caller can warn the admin — those need a manual fix
     * before the guide can safely link to a real trip.
     *
     * @return Collection<int, string> labels that couldn't be remapped
     */
    public function remapSeatsTo(BusUnit $newBusUnit): Collection
    {
        $reservations = $this->pendingSeatReservations()->with('seat')->get();
        $newSeatsByLabel = $newBusUnit->seats()->get()->keyBy('label');

        $unmatched = collect();

        foreach ($reservations as $reservation) {
            $oldLabel = $reservation->seat?->label;
            $newSeat = $oldLabel !== null ? $newSeatsByLabel->get($oldLabel) : null;

            if ($newSeat) {
                $reservation->update(['bus_unit_seat_id' => $newSeat->id]);
            } else {
                $unmatched->push($oldLabel ?? "#{$reservation->bus_unit_seat_id}");
            }
        }

        return $unmatched->unique()->values();
    }
}
