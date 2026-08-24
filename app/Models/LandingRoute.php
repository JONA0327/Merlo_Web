<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
        'price',
        'ticket_price',
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

    public function getFormattedPriceAttribute(): ?string
    {
        $price = $this->ticket_price ?? $this->price;

        if (! $price) {
            return null;
        }

        return Str::startsWith($price, '$') ? $price : '$'.$price;
    }

    public function getNumericPriceAttribute(): float
    {
        $raw = $this->ticket_price ?? $this->price;

        return (float) preg_replace('/[^0-9.]/', '', $raw ?? '0');
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

    public function hasSeatMap(): bool
    {
        return $this->bus_unit_id !== null;
    }
}
