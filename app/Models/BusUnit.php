<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'has_upper_deck',
        'canvas_width',
        'canvas_height',
        'is_active',
    ];

    protected $casts = [
        'canvas_width' => 'integer',
        'canvas_height' => 'integer',
        'is_active' => 'boolean',
        'has_upper_deck' => 'boolean',
    ];

    public function seats(): HasMany
    {
        return $this->hasMany(BusUnitSeat::class);
    }

    public function landingRoutes(): HasMany
    {
        return $this->hasMany(LandingRoute::class);
    }

    public function bookableSeatsCount(): int
    {
        return $this->seats()->bookable()->count();
    }
}
