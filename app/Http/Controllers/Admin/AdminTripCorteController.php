<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingRoute;
use App\Models\TripCorte;
use App\Models\TripCorteLine;
use App\Models\TripGuide;
use App\Services\TripCorteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTripCorteController extends Controller
{
    /** Pantalla del corte del viaje: ventas, otros conceptos del día, efectivo y envío a Control. */
    public function show(LandingRoute $landingRoute, TripCorteService $service): View
    {
        $landingRoute->load('busUnit');
        $saved = TripCorte::where('landing_route_id', $landingRoute->id)->first();
        $lines = $service->lines($landingRoute);

        return view('admin.asientos.corte', [
            'trip' => $landingRoute,
            'guide' => $landingRoute->day ? TripGuide::where('from', $landingRoute->from)->where('to', $landingRoute->to)
                ->whereDate('date_from', '<=', $landingRoute->day)->whereDate('date_to', '>=', $landingRoute->day)->first() : null,
            'corte' => $service->summarize($landingRoute),
            'corteSaved' => $saved,
            'lines' => $lines,
            'contratosCompras' => $service->contratosCompras(),
            'sinEnviar' => $saved?->sent_at && $saved->lines_signature !== $service->signature($lines, $saved),
        ]);
    }

    /** Guarda vendido sin apartar, efectivo y contrato, sin enviar. */
    public function save(Request $request, LandingRoute $landingRoute, TripCorteService $service): RedirectResponse
    {
        $d = $this->corteData($request);

        try {
            $service->save($landingRoute, $d['contrato_id'], $request->user()?->id, $d['external_seats'], $d['external_amount'], $d['cash_amount']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'No se guardó: '.$e->getMessage());
        }

        return back()->with('success', 'Datos del corte guardados.');
    }

    /** Guarda y envía el corte (con sus gastos e ingresos del día) a Control. */
    public function store(Request $request, LandingRoute $landingRoute, TripCorteService $service): RedirectResponse
    {
        $d = $this->corteData($request);

        try {
            $service->confirm($landingRoute, $d['contrato_id'], $request->user()?->id, $d['external_seats'], $d['external_amount'], $d['cash_amount']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'No se envió el corte: '.$e->getMessage());
        }

        return back()->with('success', 'Corte guardado y enviado a Control Reportes.');
    }

    public function storeLine(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.TripCorteLine::KIND_GASTO.','.TripCorteLine::KIND_INGRESO],
            'concept' => ['required', 'string', 'max:150'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:9999'],
            'unit_amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:150'],
        ]);

        $quantity = (float) ($data['quantity'] ?? 1);

        TripCorteLine::create([
            'landing_route_id' => $landingRoute->id,
            'kind' => $data['kind'],
            'concept' => $data['concept'],
            'quantity' => $quantity,
            'unit_amount' => $data['unit_amount'],
            'amount' => round($quantity * (float) $data['unit_amount'], 2),
            'note' => $data['note'] ?? null,
            'counts' => $request->boolean('counts'),
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Concepto agregado. Reenvía el corte para que se actualice en Control.');
    }

    public function destroyLine(LandingRoute $landingRoute, TripCorteLine $line): RedirectResponse
    {
        abort_unless($line->landing_route_id === $landingRoute->id, 404);
        $line->delete();

        return back()->with('success', 'Concepto eliminado. Reenvía el corte para que se actualice en Control.');
    }

    private function corteData(Request $request): array
    {
        $d = $request->validate([
            'contrato_id' => ['nullable', 'integer', 'min:1'],
            'external_seats' => ['nullable', 'integer', 'min:0'],
            'external_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return [
            'contrato_id' => isset($d['contrato_id']) ? (int) $d['contrato_id'] : null,
            'external_seats' => (int) ($d['external_seats'] ?? 0),
            'external_amount' => (float) ($d['external_amount'] ?? 0),
            'cash_amount' => isset($d['cash_amount']) && $d['cash_amount'] !== '' ? (float) $d['cash_amount'] : null,
        ];
    }
}
