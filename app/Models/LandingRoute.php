<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LandingRoute extends Model
{
    use HasFactory;

    protected $table = 'landing_routes';

    protected $fillable = [
        'from',
        'to',
        'duration',
        'day',
        'return_date',
        'departure_time',
        'available_seats',
        'bus_unit_id',
        'is_active',
        'featured',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
        'available_seats' => 'integer',
        'day' => 'date',
        'return_date' => 'date',
    ];

    public function getDepartureTimeFormattedAttribute(): ?string
    {
        if (! $this->departure_time) {
            return null;
        }

        return Carbon::parse($this->departure_time)->format('h:i A');
    }

    public function busUnit(): BelongsTo
    {
        return $this->belongsTo(BusUnit::class);
    }

    public function seatReservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function seatHolds(): HasMany
    {
        return $this->hasMany(SeatHold::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TripTicketPrice::class);
    }

    public function hasSeatMap(): bool
    {
        return $this->bus_unit_id !== null;
    }

    /**
     * Active prices keyed by trip_type for quick lookup in the
     * seat-picker (one total per type, multiplied by the seat count).
     * Inactive or missing prices are simply absent from the map.
     *
     * @return Collection<string, TripTicketPrice>
     */
    public function activePrices(): Collection
    {
        return $this->prices
            ->where('is_active', true)
            ->keyBy('trip_type');
    }

    public function priceFor(string $tripType): ?TripTicketPrice
    {
        return $this->activePrices()->get($tripType);
    }

    public function formattedPriceFor(string $tripType): ?string
    {
        $price = $this->priceFor($tripType);
        if (! $price) return null;

        $value = '$'.number_format($price->price, 2);
        return Str::startsWith($value, '$') ? $value : '$'.$value;
    }

    /**
     * Backwards-compatible single-price accessor — returns whichever
     * one-way price is on file, or null. Used by views that haven't
     * been migrated to the two-price layout yet.
     */
    public function getFormattedPriceAttribute(): ?string
    {
        return $this->formattedPriceFor(TripTicketPrice::TYPE_ONE_WAY);
    }

    public function getNumericPriceAttribute(): float
    {
        return (float) ($this->priceFor(TripTicketPrice::TYPE_ONE_WAY)?->price ?? 0);
    }

    public function numericPriceFor(string $tripType): float
    {
        return (float) ($this->priceFor($tripType)?->price ?? 0);
    }
}
