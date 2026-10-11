<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TripTicketPrice extends Model
{
    use HasFactory;

    public const TYPE_ONE_WAY = 'one_way';
    public const TYPE_ROUND_TRIP = 'round_trip';
    public const TYPE_ESPECIAL = 'especial';
    public const TYPE_REGRESO = 'regreso';

    /**
     * "Especial" is priced per mancuerna (the pair of seats sold
     * together), not per seat — the configured price covers both seats,
     * so each seat's unit_price is that amount split across the pair.
     */
    public const SEATS_PER_MANCUERNA = 2;

    protected $fillable = [
        'landing_route_id',
        'trip_type',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
    ];

    public function landingRoute(): BelongsTo
    {
        return $this->belongsTo(LandingRoute::class);
    }

    public function isOneWay(): bool
    {
        return $this->trip_type === self::TYPE_ONE_WAY;
    }

    public function isRoundTrip(): bool
    {
        return $this->trip_type === self::TYPE_ROUND_TRIP;
    }

    public function getFormattedPriceAttribute(): string
    {
        $value = '$'.number_format($this->price, 2);
        return Str::startsWith($value, '$') ? $value : '$'.$value;
    }

    /**
     * Best-guess per-seat price before a real trip exists to price off
     * of (a Guía apartado, staged or auto-provisioned from a standing
     * assignment) — the global default, mancuerna-split the same way
     * LandingRoute::seatPriceFor() does for "especial". Whoever created
     * it this way gets corrected for real once an actual trip opens
     * (TripGuide::linkTrip() / StandingReservation::applyToTrip()).
     */
    public static function estimateUnitPrice(string $tripType, int $seatCount = self::SEATS_PER_MANCUERNA): float
    {
        $price = (float) (Setting::current()->defaultPrices()[$tripType] ?? 0);

        if ($tripType !== self::TYPE_ESPECIAL) {
            return $price;
        }

        $seatCount = max(1, $seatCount);
        $mancuernas = (int) ceil($seatCount / self::SEATS_PER_MANCUERNA);

        return round($price * $mancuernas / $seatCount, 2);
    }

    public static function tripTypes(): array
    {
        return [
            self::TYPE_ONE_WAY => 'Solo ida',
            self::TYPE_ROUND_TRIP => 'Viaje redondo',
            self::TYPE_ESPECIAL => 'Viaje especial',
            self::TYPE_REGRESO => 'De regreso',
        ];
    }
}
