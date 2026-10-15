<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageCorte;
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
            'pending' => $service->pendingUnits(),
        ]);
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
