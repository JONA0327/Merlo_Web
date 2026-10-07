<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email'];

    /**
     * Called from the apartado flow so every customer typed in by hand
     * gets remembered automatically — the agenda grows on its own, not
     * just from the dedicated admin screen. Updates the name/email on
     * file if a returning customer's phone matches but details changed.
     */
    public static function remember(string $name, string $phone, ?string $email = null): void
    {
        static::updateOrCreate(['phone' => $phone], array_filter([
            'name' => $name,
            'email' => $email,
        ], fn ($v) => $v !== null));
    }
}
