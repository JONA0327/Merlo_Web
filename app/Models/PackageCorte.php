<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageCorte extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'sent_at' => 'datetime'];
    }
}
