<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageCorte;
use App\Models\PackageExpense;
use App\Models\PackagePayment;
use App\Services\PackageCorteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPackageCorteController extends Controller
{
    public function show(Request $request, PackageCorteService $service): View
    {
        $date = Carbon::parse($request->query('fecha', today()->toDateString()))->startOfDay();

        return view('admin.paqueteria.corte', [
            'date' => $date,
            'payments' => $service->payments($date),
            'expenses' => $service->expenses($date),
            'corte' => PackageCorte::whereDate('fecha', $date)->first(),
        ]);
    }

    /** Lista imprimible: quién pagó ese día y quién sigue debiendo. */
    public function print(Request $request, PackageCorteService $service): View
    {
        $date = Carbon::parse($request->query('fecha', today()->toDateString()))->startOfDay();

        return view('admin.paqueteria.corte-imprimir', [
            'date' => $date,
            'payments' => $service->payments($date),
            'expenses' => $service->expenses($date),
            'pending' => $service->pendingUnits(),
        ]);
    }

    /**
     * Corrige un cobro ya registrado (monto y método). En envíos sin folio
     * también se puede cambiar nombre, costo y cantidad de paquetes.
     */
    public function updatePayment(Request $request, PackagePayment $payment): RedirectResponse
    {
        $unit = $payment->payable;
        $legacy = $unit instanceof Package && $unit->folio_number === null;

        $data = $request->validate([
            'amount' => ['required_unless:method,gratis', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'method' => ['required', $legacy ? 'in:efectivo,transferencia,tarjeta,gratis' : 'in:efectivo,transferencia,tarjeta'],
            'client_name' => [$legacy ? 'required' : 'prohibited', 'string', 'max:255'],
            'price' => [$legacy ? 'required_unless:method,gratis' : 'prohibited', 'nullable', 'numeric', 'min:0'],
            'package_count' => [$legacy ? 'required' : 'prohibited', 'integer', 'min:1', 'max:1000'],
        ]);

        // Gratis (solo sin folio): cobro y costo en $0.
        if ($data['method'] === 'gratis') {
            $data['amount'] = 0;
            $data['price'] = 0;
        } elseif ((float) $data['amount'] <= 0) {
            return back()->withErrors(['amount' => 'El monto debe ser mayor a 0 (o elige Gratis).']);
        }

        $newAmount = round((float) $data['amount'], 2);
        $newPaid = round((float) $unit->amount_paid - (float) $payment->amount + $newAmount, 2);
        $billed = $legacy ? (float) $data['price'] : $unit->billedAmount();

        if ($billed !== null && $newPaid - $billed > 0.001) {
            return back()->withErrors(['amount' => 'Lo pagado no puede ser mayor al costo ($'.number_format($billed, 2).').']);
        }

        DB::transaction(function () use ($payment, $unit, $data, $newAmount, $newPaid, $legacy) {
            $payment->update(['amount' => $newAmount, 'method' => $data['method']]);
            $unit->amount_paid = $newPaid;
            if ($legacy) {
                $unit->fill([
                    'client_name' => trim($data['client_name']),
                    'price' => $data['price'],
                    'package_count' => (int) $data['package_count'],
                ]);
            }
            $unit->save();
        });

        return redirect()->route('admin.paqueteria.corte', ['fecha' => $payment->paid_at->toDateString()])
            ->with('success', 'Cobro actualizado. Reenvía el corte para que se actualice en Control.');
    }

    /** Borra un cobro del corte; si es un envío sin folio, se borra el envío completo. */
    public function destroyPayment(PackagePayment $payment): RedirectResponse
    {
        $unit = $payment->payable;
        $fecha = $payment->paid_at->toDateString();

        DB::transaction(function () use ($payment, $unit) {
            if ($unit instanceof Package && $unit->folio_number === null) {
                $unit->payments()->delete();
                $unit->delete();

                return;
            }

            $unit->amount_paid = max(0, round((float) $unit->amount_paid - (float) $payment->amount, 2));
            $unit->save();
            $payment->delete();
        });

        return redirect()->route('admin.paqueteria.corte', ['fecha' => $fecha])
            ->with('success', 'Cobro eliminado. Reenvía el corte para que se actualice en Control.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
        ]);

        PackageExpense::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.paqueteria.corte', ['fecha' => $data['fecha']])
            ->with('success', 'Egreso agregado. Reenvía el corte para que se actualice en Control.');
    }

    public function destroyExpense(PackageExpense $expense): RedirectResponse
    {
        $fecha = $expense->fecha->toDateString();
        $expense->delete();

        return redirect()->route('admin.paqueteria.corte', ['fecha' => $fecha])
            ->with('success', 'Egreso eliminado. Reenvía el corte para que se actualice en Control.');
    }

    public function store(Request $request, PackageCorteService $service): RedirectResponse
    {
        $data = $request->validate(['fecha' => ['required', 'date']]);
        $date = Carbon::parse($data['fecha'])->startOfDay();

        try {
            $service->confirm($date, $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se envió el corte: '.$e->getMessage());
        }

        return back()->with('success', 'Corte de paquetería enviado a Control Reportes.');
    }
}
