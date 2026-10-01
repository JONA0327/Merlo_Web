<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'facebook_url',
        'instagram_url',
        'return_resale_validity_hours',
        'evolution_api_url',
        'evolution_api_key',
        'evolution_instance',
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
}
