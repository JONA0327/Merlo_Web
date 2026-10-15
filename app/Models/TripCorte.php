<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripCorte extends Model
{
    protected $guarded = [];

    protected $casts = ['fecha' => 'date', 'sent_at' => 'datetime'];
}
