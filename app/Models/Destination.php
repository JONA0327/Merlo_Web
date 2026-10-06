<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Restrict to rows the operator should see in selects — turned-off
     * destinations stop showing up but their old trips keep working.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Trips that use this destination's name in their `from` / `to`
     * columns. Those are free-text columns (not FKs) — matching by
     * `name` works because the trip form is the only place those
     * columns are written and it pulls from destinations.
     */
    public function tripsAsOrigin(): HasMany
    {
        return $this->hasMany(LandingRoute::class, 'from', 'name');
    }

    public function tripsAsDestination(): HasMany
    {
        return $this->hasMany(LandingRoute::class, 'to', 'name');
    }
}