<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'openpay_customer_id',
        'openpay_card_id',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'cardholder_name',
        'is_default',
    ];

    protected $casts = [
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-readable card description for the wallet/checkout UI.
     * Visa •••• 4242 (08/27) — only the non-sensitive bits OpenPay
     * gives us, which is exactly what PCI allows us to store.
     */
    public function getDisplayLabelAttribute(): string
    {
        $brand = strtoupper($this->card_brand ?? 'Tarjeta');
        $last4 = $this->card_last4 ? str_repeat('•', 4).' '.str_pad($this->card_last4, 4, '•', STR_PAD_LEFT) : '•••• ••••';
        $exp = ($this->card_exp_month && $this->card_exp_year)
            ? sprintf(' (%02d/%02d)', $this->card_exp_month, $this->card_exp_year % 100)
            : '';
        return $brand.' '.$last4.$exp;
    }
}
