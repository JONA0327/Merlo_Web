<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageExpense extends Model
{
    protected $fillable = ['fecha', 'concept', 'amount', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
