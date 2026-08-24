<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'facebook_url',
        'instagram_url',
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
}
