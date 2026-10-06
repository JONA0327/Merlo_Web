<?php

namespace App\Models;

use App\Models\TripTicketPrice;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'facebook_url',
        'instagram_url',
        'return_resale_validity_hours',
        'default_price_one_way',
        'default_price_round_trip',
        'default_price_especial',
        'default_price_regreso',
        'evolution_api_url',
        'evolution_api_key',
        'evolution_instance',
        'boarding_outbound_legend',
        'boarding_return_legend',
    ];

    protected $casts = [
        'default_price_one_way' => 'float',
        'default_price_round_trip' => 'float',
        'default_price_especial' => 'float',
        'default_price_regreso' => 'float',
    ];

    /**
     * Site-wide settings live in a single row — there's only ever one site.
     * Callers never need to know or care about the row's id.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    /**
     * Digits only, no "+", no spaces — the exact format wa.me links need.
     * Kept forgiving on input so whoever fills the settings form doesn't
     * have to think about formatting.
     */
    public function whatsappDigits(): ?string
    {
        if (! $this->whatsapp_number) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->whatsapp_number);

        return $digits !== '' ? $digits : null;
    }

    /**
     * How many hours a released return leg stays purchasable before
     * the resale window closes. Falls back to the column default
     * (24) if somehow null.
     */
    public function returnResaleValidityHours(): int
    {
        return (int) ($this->return_resale_validity_hours ?? 24);
    }

    /**
     * Whether enough is filled in to talk to Evolution API at all — the
     * instance itself might still be unconnected (no phone scanned yet),
     * that's a separate, live check against the API.
     */
    public function evolutionConfigured(): bool
    {
        return filled($this->evolution_api_url)
            && filled($this->evolution_api_key)
            && filled($this->evolution_instance);
    }

    /**
     * Default price for a given ticket type (the global fallback used
     * by LandingRoute::priceFor when a trip has no explicit override).
     * Returns null when no default is set yet, which makes the seat
     * picker / search results render "—" instead of $0.00 for that
     * type until the admin configures one.
     */
    public function defaultPriceFor(string $tripType): ?float
    {
        $column = match ($tripType) {
            TripTicketPrice::TYPE_ONE_WAY => 'default_price_one_way',
            TripTicketPrice::TYPE_ROUND_TRIP => 'default_price_round_trip',
            TripTicketPrice::TYPE_ESPECIAL => 'default_price_especial',
            TripTicketPrice::TYPE_REGRESO => 'default_price_regreso',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $value = $this->{$column};

        return $value !== null && $value > 0 ? (float) $value : null;
    }

    /**
     * Boarding-point legend lines shown on tickets / WhatsApp notices.
     * Admin-editable from Configuraciones; falls back to the original
     * hardcoded defaults (SeatReservation::DEFAULT_*_MEETING_POINT)
     * when left blank, so an empty settings row doesn't produce a
     * ticket with no boarding instructions at all.
     */
    public function outboundMeetingPoint(): string
    {
        return filled($this->boarding_outbound_legend)
            ? $this->boarding_outbound_legend
            : SeatReservation::DEFAULT_OUTBOUND_MEETING_POINT;
    }

    public function returnMeetingPoint(): string
    {
        return filled($this->boarding_return_legend)
            ? $this->boarding_return_legend
            : SeatReservation::DEFAULT_RETURN_MEETING_POINT;
    }

    /**
     * Map of trip_type => default price, in the same shape used by
     * LandingRoute::activePrices() so views can iterate uniformly.
     *
     * @return array<string, float>
     */
    public function defaultPrices(): array
    {
        return array_filter([
            TripTicketPrice::TYPE_ONE_WAY => $this->defaultPriceFor(TripTicketPrice::TYPE_ONE_WAY),
            TripTicketPrice::TYPE_ROUND_TRIP => $this->defaultPriceFor(TripTicketPrice::TYPE_ROUND_TRIP),
            TripTicketPrice::TYPE_ESPECIAL => $this->defaultPriceFor(TripTicketPrice::TYPE_ESPECIAL),
            TripTicketPrice::TYPE_REGRESO => $this->defaultPriceFor(TripTicketPrice::TYPE_REGRESO),
        ], fn ($v) => $v !== null);
    }
}
