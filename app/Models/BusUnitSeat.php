<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusUnitSeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_unit_id',
        'deck',
        'kind',
        'label',
        'type',
        'shape',
        'width',
        'height',
        'corner_radius',
        'border_width',
        'color',
        'pos_x',
        'pos_y',
    ];

    protected $casts = [
        'pos_x' => 'float',
        'pos_y' => 'float',
        'width' => 'integer',
        'height' => 'integer',
        'corner_radius' => 'integer',
        'border_width' => 'integer',
    ];

    public function busUnit(): BelongsTo
    {
        return $this->belongsTo(BusUnit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function isBookable(): bool
    {
        return $this->kind === 'seat' && $this->type !== 'disabled';
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('kind', 'seat')->where('type', '!=', 'disabled');
    }
}
