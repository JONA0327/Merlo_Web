<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageCorte;
use App\Models\PackageExpense;
use App\Services\PackageCorteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
