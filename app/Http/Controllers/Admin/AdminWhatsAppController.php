<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\EvolutionWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AdminWhatsAppController extends Controller
{
    public function edit(): View
    {
        return view('admin.whatsapp', [
            'setting' => Setting::current(),
        ]);
    }

    /**
     * Saves the connection details (server URL, apikey, instance name).
     * Just the config — actually connecting/scanning happens via the
     * AJAX endpoints below, on the same page, right after saving.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'evolution_api_url' => ['required', 'string', 'max:255'],
            'evolution_api_key' => ['required', 'string', 'max:255'],
            'evolution_instance' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
        ], [
            'evolution_instance.regex' => 'El nombre de instancia solo puede tener letras, números, guiones y guiones bajos.',
        ]);

        Setting::current()->update($validated);

        return redirect()->route('admin.whatsapp.edit')->with('success', 'Datos de conexión guardados. Ahora pulsa "Generar código QR" para vincular el teléfono.');
    }

    /**
     * AJAX: create the instance if needed and return a fresh QR code to
     * scan. Called when the admin clicks "Generar código QR" and again
     * automatically while the page polls for connection.
     */
    public function qrCode(EvolutionWhatsAppService $whatsapp): JsonResponse
    {
        try {
            $qr = $whatsapp->fetchQrCode();

            return response()->json([
                'qr' => $qr,
                'connected' => $qr === null,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * AJAX: live connection state, polled by the page while a QR is on
     * screen so it can flip to "Conectado" the instant the phone finishes
     * scanning, without the admin having to refresh anything.
     */
    public function status(EvolutionWhatsAppService $whatsapp): JsonResponse
    {
        try {
            return response()->json(['state' => $whatsapp->connectionState()]);
        } catch (Throwable $e) {
            return response()->json(['state' => 'unknown', 'error' => $e->getMessage()]);
        }
    }

    public function disconnect(EvolutionWhatsAppService $whatsapp): RedirectResponse
    {
        try {
            $whatsapp->disconnect();

            return back()->with('success', 'WhatsApp desconectado. Genera un nuevo código QR cuando quieras volver a vincular el teléfono.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
