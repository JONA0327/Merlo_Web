<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
        'image',
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

        return Carbon::parse($this->departure_time)->format('H:i');
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
     * Whether this trip's travel date is already in the past — the
     * return date if there is one (the trip isn't "over" until the
     * return leg happened), otherwise the departure date. A trip with
     * no date at all ("Sin fecha") never closes on its own; an admin
     * has to deactivate it manually via is_active.
     */
    public function hasEnded(): bool
    {
        $lastDate = $this->return_date ?? $this->day;

        return $lastDate !== null && $lastDate->lt(today());
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
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
        // Per-trip override (active) wins; fall back to the global
        // default when the admin never gave this trip its own row, or
        // when the only existing row was turned off ("Visible=false").
        $override = $this->activePrices()->get($tripType);
        if ($override) {
            return $override;
        }

        $default = Setting::current()->defaultPriceFor($tripType);
        if ($default === null) {
            return null;
        }

        // Synthesize a TripTicketPrice-like row so callers don't have to
        // branch on "is this an override or the default?". Carries
        // enough info for the accessors below; nothing else in the
        // app calls methods beyond `->price` / `->trip_type` on it.
        $synthetic = new TripTicketPrice();
        $synthetic->setRawAttributes([
            'landing_route_id' => $this->id,
            'trip_type' => $tripType,
            'price' => $default,
            'is_active' => true,
        ], true);

        return $synthetic;
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

    /**
     * What ONE seat costs for this trip type — the value stored as a
     * reservation's unit_price. Same as numericPriceFor() except for
     * "especial", whose configured price is for a whole mancuerna
     * (e.g. $1,800 for seats 1 y 2 together): an apartado of
     * $seatCount especial seats is charged one mancuerna per started
     * pair — 1 or 2 seats = $1,800, 3 or 4 = $3,600 — split evenly so
     * the seats add back up to that total.
     */
    public function seatPriceFor(string $tripType, int $seatCount = TripTicketPrice::SEATS_PER_MANCUERNA): float
    {
        $price = $this->numericPriceFor($tripType);

        if ($tripType !== TripTicketPrice::TYPE_ESPECIAL) {
            return $price;
        }

        $seatCount = max(1, $seatCount);
        $mancuernas = (int) ceil($seatCount / TripTicketPrice::SEATS_PER_MANCUERNA);

        return round($price * $mancuernas / $seatCount, 2);
    }

    /**
     * Whether the given type currently has a per-trip OVERRIDE
     * (distinct from inheriting the global default). Used by the
     * admin "Precios de boleto" screen to render "(default)" hints
     * next to cells that don't have their own row.
     */
    public function hasOverrideFor(string $tripType): bool
    {
        return $this->prices()->where('trip_type', $tripType)->exists();
    }
}
