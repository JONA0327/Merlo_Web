<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'landing_route_id',
        'bus_unit_seat_id',
        'user_id',
        'session_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
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

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Single comparable string identifying who holds this seat.
     * Either 'u:{id}' (logged-in user) or 's:{session_id}' (guest).
     * Used by SeatPickerController::show to build the payload the
     * JS compares against the visitor's own holder_id — without
     * prefixing, an attacker who knew their own session_id could
     * spoof a 'u:1' string to match a logged-in holder.
     */
    public function getHolderIdAttribute(): string
    {
        if ($this->user_id !== null) {
            return 'u:'.$this->user_id;
        }
        if ($this->session_id !== null) {
            return 's:'.$this->session_id;
        }
        // Should never happen: schema guarantees one of the two is set.
        return '?:0';
    }

    /**
     * Compute the holder id for an arbitrary visitor (logged-in or
     * not) using the same scheme SeatHold::$datesetter writes.
     * Used by the controller to compare against the holder_id of an
     * existing hold (the "is this MY hold or someone else's?" check).
     */
    public static function holderIdFor(?int $userId, ?string $sessionId): ?string
    {
        if ($userId !== null) {
            return 'u:'.$userId;
        }
        if ($sessionId !== null) {
            return 's:'.$sessionId;
        }
        return null;
    }
}
