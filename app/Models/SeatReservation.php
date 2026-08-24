<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'landing_route_id',
        'bus_unit_seat_id',
        'user_id',
    ];

    public function landingRoute(): BelongsTo
    {
        return $this->belongsTo(LandingRoute::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(BusUnitSeat::class, 'bus_unit_seat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
