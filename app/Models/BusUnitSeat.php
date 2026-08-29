<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusUnitSeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_unit_id',
        'deck',
        'kind',
        'allowed_trip_type',
        'label',
        'type',
        'shape',
        'width',
        'height',
        'corner_radius',
        'border_width',
        'color',
        'pos_x',
        'pos_y',
    ];

    protected $casts = [
        'pos_x' => 'float',
        'pos_y' => 'float',
        'width' => 'integer',
        'height' => 'integer',
        'corner_radius' => 'integer',
        'border_width' => 'integer',
    ];

    public const ALLOWED_BOTH = 'both';
    public const ALLOWED_ONE_WAY = 'one_way';
    public const ALLOWED_ROUND_TRIP = 'round_trip';

    public function busUnit(): BelongsTo
    {
        return $this->belongsTo(BusUnit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function isBookable(): bool
    {
        return $this->kind === 'seat' && $this->type !== 'disabled';
    }

    /**
     * Whether this seat can be picked when the customer (or admin
     * apartado) is buying the given ticket type. Seats flagged as
     * 'both' are always bookable; the type-specific values restrict
     * a seat to that single ticket type.
     */
    public function allowsTripType(string $tripType): bool
    {
        $allowed = $this->allowed_trip_type ?? self::ALLOWED_BOTH;
        if ($allowed === self::ALLOWED_BOTH) return true;
        return $allowed === $tripType;
    }

    public static function allowedTripTypes(): array
    {
        return [
            self::ALLOWED_BOTH => 'Ambos (ida y redondo)',
            self::ALLOWED_ONE_WAY => 'Solo ida',
            self::ALLOWED_ROUND_TRIP => 'Solo redondo',
        ];
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('kind', 'seat')->where('type', '!=', 'disabled');
    }
}
