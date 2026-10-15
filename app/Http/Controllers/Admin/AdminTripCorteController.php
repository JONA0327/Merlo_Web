<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingRoute;
use App\Services\TripCorteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminTripCorteController extends Controller
{
    public function store(Request $request, LandingRoute $landingRoute, TripCorteService $service): RedirectResponse
    {
        $data = $request->validate([
            'contrato_id' => ['nullable', 'integer', 'min:1'],
            'external_seats' => ['nullable', 'integer', 'min:0'],
            'external_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $service->confirm($landingRoute, $data['contrato_id'] ?? null, $request->user()?->id, (int) ($data['external_seats'] ?? 0), (float) ($data['external_amount'] ?? 0));
        } catch (\Throwable $e) {
            return back()->with('error', 'No se envió el corte: '.$e->getMessage());
        }

        return back()->with('success', 'Corte guardado y enviado a Control Reportes.');
    }
}
