<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageCorte;
use App\Models\PackageExpense;
use App\Models\PackageGroup;
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

    /** Egresos del día: lo que se dio de lo recaudado. */
    public function expenses(Carbon $date)
    {
        return PackageExpense::whereDate('fecha', $date)->orderBy('id')->get();
    }

    /**
     * Envíos ya entregados con saldo por cobrar (los de Clientes y los
     * registrados sin folio), para la lista impresa del corte.
     *
     * @return \Illuminate\Support\Collection<int, Package|PackageGroup>
     */
    public function pendingUnits()
    {
        $groups = PackageGroup::with('packages')->get()
            ->filter(fn (PackageGroup $g) => $g->packages->contains('status', Package::STATUS_ENTREGADO));
        $packages = Package::whereNull('package_group_id')->where('status', Package::STATUS_ENTREGADO)->get();

        return $groups->concat($packages)->filter(fn ($u) => $u->pendingAmount() > 0)->values();
    }

    public function confirm(Carbon $date, ?int $userId): PackageCorte
    {
        $payments = $this->payments($date);
        $total = round((float) $payments->sum('amount'), 2);
        $expenses = $this->expenses($date);
        $expensesTotal = round((float) $expenses->sum('amount'), 2);

        app(TripCorteService::class)->http()->post('/merlo/cortes-paqueteria', [
            'fecha' => $date->toDateString(),
            'payments_count' => $payments->count(),
            'total_amount' => $total,
            // Egresos del día; Control los registra como egreso, empatados con el ingreso de esa fecha.
            'expenses' => $expenses->map(fn ($e) => ['id' => $e->id, 'concept' => $e->concept, 'amount' => (float) $e->amount])->values()->all(),
        ])->throw();

        // whereDate: la columna `fecha` se guarda con hora en SQLite, un where exacto no la encuentra.
        $corte = PackageCorte::whereDate('fecha', $date)->first() ?? new PackageCorte(['fecha' => $date->toDateString()]);
        $corte->fill([
            'payments_count' => $payments->count(), 'total_amount' => $total,
            'expenses_count' => $expenses->count(), 'expenses_amount' => $expensesTotal,
            'created_by' => $userId, 'sent_at' => now(),
        ])->save();

        return $corte;
    }
}
