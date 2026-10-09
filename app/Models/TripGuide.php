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

    /**
     * The guide a reservation for this route on $date should be staged
     * in: one already covering the date; else the nearest one for this
     * route, stretched to reach it; only creating a brand-new one when
     * the route has none at all — keeps every staged apartado for a
     * route under ONE guide instead of spawning one per date. Not
     * filtered by bus_unit_id: the "plantilla" may have changed (see
     * AdminTripGuideController::update()), and the guide actually there
     * for this date is still the right place. Only a brand-new guide
     * defaults to $busUnitId. Call inside a transaction (rows locked).
     */
    public static function forRouteOnDate(string $from, string $to, ?int $busUnitId, \Carbon\Carbon $date, string $notes): self
    {
        $routeGuides = static::query()
            ->where('from', $from)
            ->where('to', $to)
            ->lockForUpdate()
            ->get();

        // whereDate()-equivalent in PHP (comparing Carbon values, not
        // raw SQL strings): a raw where('date_from', '<=', ...) once
        // silently never matched because the column stores a full
        // "Y-m-d 00:00:00" datetime that sorts after a bare "Y-m-d".
        $guide = $routeGuides->first(fn (self $g) => $g->coversDate($date));

        if (! $guide) {
            $guide = $routeGuides->sortBy(fn (self $g) => min(
                abs($g->date_from->diffInDays($date)),
                abs($g->date_to->diffInDays($date))
            ))->first();

            if ($guide) {
                $guide->update([
                    'date_from' => $date->lt($guide->date_from) ? $date->toDateString() : $guide->date_from,
                    'date_to' => $date->gt($guide->date_to) ? $date->toDateString() : $guide->date_to,
                ]);
            }
        }

        return $guide ?? static::create([
            'from' => $from,
            'to' => $to,
            'bus_unit_id' => $busUnitId,
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
            'notes' => $notes,
        ]);
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
     * Also checks the REVERSE direction: a return leg staged via
     * SeatReservation::createReturnLegTicket() lives on the SAME guide as
     * its outbound ticket (not a separate reversed guide — the user's
     * call, since it's conceptually "the same trip, just the way back"),
     * marked by having a source_reservation_id. Those are only ever
     * destined for a trip going the opposite way, so they're matched
     * here against THIS trip when this trip is that reverse direction —
     * never against a same-direction trip, even though they sit in a
     * same-direction guide.
     *
     * The guide's bus unit no longer has to match the trip's exactly —
     * an admin can open the real trip on a different "plantilla" than the
     * guide assumed. Matching reservations get remapped by seat LABEL
     * onto the trip's actual bus (see remapReservationForTrip()); any
     * that can't be (label doesn't exist on the new bus, or it's already
     * taken there) still get linked — so they show up in Apartar asientos
     * for the admin to fix — but are flagged by
     * SeatReservation::hasSeatMismatch() and never get the automatic
     * WhatsApp send, and are returned as 'unmatched' so the caller can
     * warn right away.
     *
     * @return array{linked: int, unmatched: array<int, string>}
     */
    public static function linkTrip(LandingRoute $trip): array
    {
        if ($trip->day === null || $trip->bus_unit_id === null) {
            return ['linked' => 0, 'unmatched' => []];
        }

        $linked = 0;
        $rootIds = [];
        $mismatchedRootIds = [];
        $unmatchedLabels = [];

        $forwardGuides = static::query()
            ->where('from', $trip->from)
            ->where('to', $trip->to)
            ->where('date_from', '<=', $trip->day)
            ->where('date_to', '>=', $trip->day)
            ->get();

        foreach ($forwardGuides as $guide) {
            $reservations = $guide->pendingSeatReservations()
                ->whereNull('source_reservation_id')
                ->whereDate('travel_date', $trip->day)
                ->with('seat')
                ->get();

            foreach ($reservations as $reservation) {
                $matched = static::remapReservationForTrip($reservation, $trip);
                $reservation->landing_route_id = $trip->id;
                if ($matched) {
                    $reservation->unit_price = (float) ($trip->priceFor($reservation->trip_type)?->price ?? 0);
                } else {
                    $unmatchedLabels[] = $reservation->seat?->label ?? "#{$reservation->bus_unit_seat_id}";
                }
                $reservation->save();

                $rootId = static::rootIdFor($reservation);
                $rootIds[] = $rootId;
                if (! $matched) {
                    $mismatchedRootIds[] = $rootId;
                }
                $linked++;
            }
        }

        $reverseGuides = static::query()
            ->where('from', $trip->to)
            ->where('to', $trip->from)
            ->where('date_from', '<=', $trip->day)
            ->where('date_to', '>=', $trip->day)
            ->get();

        foreach ($reverseGuides as $guide) {
            $reservations = $guide->pendingSeatReservations()
                ->whereNotNull('source_reservation_id')
                ->whereDate('travel_date', $trip->day)
                ->with('seat')
                ->get();

            foreach ($reservations as $reservation) {
                // Price was already fixed when the return was agendado
                // (free for a round-trip/especial reschedule, or charged
                // the "De regreso" price for an ida) — don't recompute it
                // off the now-real trip's own pricing, just the seat.
                $matched = static::remapReservationForTrip($reservation, $trip);
                $reservation->landing_route_id = $trip->id;
                if (! $matched) {
                    $unmatchedLabels[] = $reservation->seat?->label ?? "#{$reservation->bus_unit_seat_id}";
                }
                $reservation->save();

                $rootId = static::rootIdFor($reservation);
                $rootIds[] = $rootId;
                if (! $matched) {
                    $mismatchedRootIds[] = $rootId;
                }
                $linked++;
            }
        }

        // One queued notification per GROUP (not per seat) — a 4-seat
        // apartado staged in the guide should send one combined ticket,
        // not four separate WhatsApp messages. Queued rather than sent
        // here so opening a trip that resolves several groups at once
        // doesn't fire them all synchronously or back to back. A group
        // with ANY unmatched seat is skipped entirely — reassigning that
        // seat from Apartar asientos re-dispatches it (see
        // AdminSeatReservationController::reassignSeat()).
        foreach (array_unique($rootIds) as $rootId) {
            if (in_array($rootId, $mismatchedRootIds, true)) {
                continue;
            }
            \App\Jobs\SendLinkedTicketNotification::dispatch($rootId);
        }

        return ['linked' => $linked, 'unmatched' => array_values(array_unique($unmatchedLabels))];
    }

    /**
     * Makes sure $reservation's bus_unit_seat_id actually belongs to the
     * trip's bus unit — trivially true when the guide was staged on the
     * same bus already, otherwise looks up the same-labeled seat on the
     * trip's bus and re-points to it. Skips the remap (leaves the stale
     * seat id, flagged by hasSeatMismatch()) when no same-labeled seat
     * exists on the new bus, or another reservation on this trip already
     * holds it for the same leg.
     */
    public static function remapReservationForTrip(SeatReservation $reservation, LandingRoute $trip): bool
    {
        if ($reservation->seat && $reservation->seat->bus_unit_id === $trip->bus_unit_id) {
            return true;
        }

        $label = $reservation->seat?->label;
        $newSeat = $label !== null ? $trip->busUnit->seats()->where('label', $label)->first() : null;

        if (! $newSeat) {
            return false;
        }

        $taken = SeatReservation::where('landing_route_id', $trip->id)
            ->where('bus_unit_seat_id', $newSeat->id)
            ->where('leg', $reservation->leg)
            ->exists();

        if ($taken) {
            return false;
        }

        $reservation->bus_unit_seat_id = $newSeat->id;

        return true;
    }

    /**
     * Plantilla switched on a trip that already has reservations: re-point
     * each one to the same-labeled seat on the new bus (guide-staged
     * apartados first, then oldest first, so on a clash the earlier booking
     * keeps the seat). Seats whose
     * label doesn't exist, or that an earlier booking already took, stay
     * flagged by hasSeatMismatch() for manual reassignment.
     *
     * @return array<int, string> labels that couldn't be remapped
     */
    public static function remapTripReservations(LandingRoute $trip): array
    {
        $unmatched = [];

        // Guide-staged apartados (trip_guide_id kept after linking) were
        // booked first, so they win a clash over ones added on the trip.
        $trip->seatReservations()->with('seat')->orderByRaw('trip_guide_id is null')->orderBy('created_at')->orderBy('id')->get()
            ->each(function (SeatReservation $r) use ($trip, &$unmatched) {
                if (! static::remapReservationForTrip($r, $trip)) {
                    $unmatched[] = $r->seat?->label ?? "#{$r->bus_unit_seat_id}";
                } elseif ($r->isDirty('bus_unit_seat_id')) {
                    $r->save();
                }
            });

        return array_values(array_unique($unmatched));
    }

    private static function rootIdFor(SeatReservation $reservation): int
    {
        return $reservation->groupRootId();
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
