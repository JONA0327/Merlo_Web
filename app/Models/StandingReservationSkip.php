<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "No viaja hoy" — see StandingReservation::applyToTrip() and
 * AdminSeatReservationController::releaseStanding(). One row means "this
 * standing assignment doesn't apply to this one trip," without touching
 * the assignment itself.
 */
class StandingReservationSkip extends Model
{
    protected $fillable = [
        'standing_reservation_id',
        'landing_route_id',
    ];

    public function standingReservation(): BelongsTo
    {
        return $this->belongsTo(StandingReservation::class);
    }

    public function landingRoute(): BelongsTo
    {
        return $this->belongsTo(LandingRoute::class);
    }
}
