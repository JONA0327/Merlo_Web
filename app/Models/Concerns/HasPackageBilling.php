<?php

namespace App\Models\Concerns;

use App\Models\PackagePayment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Cobro de un envío (un Package suelto o un PackageGroup): el costo total,
 * lo ya pagado (amount_paid) y lo pendiente, que se calcula. Cada pago deja
 * un renglón en package_payments, que es lo que el corte diario manda a Control.
 */
trait HasPackageBilling
{
    /** Costo total del envío; null mientras no se capture. */
    abstract public function billedAmount(): ?float;

    public function payments(): MorphMany
    {
        return $this->morphMany(PackagePayment::class, 'payable');
    }

    public function pendingAmount(): float
    {
        return max(0.0, round((float) $this->billedAmount() - (float) $this->amount_paid, 2));
    }

    public function registerPayment(float $amount, ?int $userId): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($amount, $userId) {
            $this->payments()->create(['amount' => $amount, 'user_id' => $userId, 'paid_at' => now()]);
            $this->increment('amount_paid', $amount);
        });
    }
}
