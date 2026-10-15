<?php

namespace App\Services;

use App\Models\PackageCorte;
use App\Models\PackagePayment;
use Illuminate\Support\Carbon;

/**
 * Corte diario de paquetería: suma lo cobrado ese día (pagos al entregar y
 * saldos cobrados después) y lo manda a Control como un ingreso de
 * paquetería. No va ligado a contratos, así que entra directo.
 */
class PackageCorteService
{
    public function payments(Carbon $date)
    {
        return PackagePayment::with('payable', 'user')->whereDate('paid_at', $date)->orderBy('paid_at')->get();
    }

    public function confirm(Carbon $date, ?int $userId): PackageCorte
    {
        $payments = $this->payments($date);
        $total = round((float) $payments->sum('amount'), 2);

        app(TripCorteService::class)->http()->post('/merlo/cortes-paqueteria', [
            'fecha' => $date->toDateString(),
            'payments_count' => $payments->count(),
            'total_amount' => $total,
        ])->throw();

        // whereDate: la columna `fecha` se guarda con hora en SQLite, un where exacto no la encuentra.
        $corte = PackageCorte::whereDate('fecha', $date)->first() ?? new PackageCorte(['fecha' => $date->toDateString()]);
        $corte->fill(['payments_count' => $payments->count(), 'total_amount' => $total, 'created_by' => $userId, 'sent_at' => now()])->save();

        return $corte;
    }
}
