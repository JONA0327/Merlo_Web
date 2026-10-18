<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gasto u otro ingreso del día de un viaje (renglón del corte). */
class TripCorteLine extends Model
{
    public const KIND_GASTO = 'gasto';

    public const KIND_INGRESO = 'ingreso';

    protected $fillable = ['landing_route_id', 'kind', 'concept', 'quantity', 'unit_amount', 'amount', 'note', 'counts', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_amount' => 'decimal:2', 'amount' => 'decimal:2', 'counts' => 'boolean'];
    }
}
