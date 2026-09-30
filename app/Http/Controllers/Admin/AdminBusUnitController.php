<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBusUnitController extends Controller
{
    public function index(): View
    {
        return view('admin.unidades.index', [
            'busUnits' => BusUnit::withCount('seats')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $busUnit = BusUnit::create($validated);

        return redirect()->route('admin.unidades.edit', $busUnit)->with('success', 'Unidad creada. Ahora agrega los asientos.');
    }

    public function edit(BusUnit $busUnit): View
    {
        $busUnit->load('seats');

        return view('admin.unidades.editor', [
            'busUnit' => $busUnit,
        ]);
    }

    /**
     * Also called via AJAX from the seat editor's main "Guardar
     * distribución" button — that one save action now persists both
     * the unit's own data (this) and the seat layout in one go, so an
     * admin editing the name/description no longer has to notice the
     * separate, easy-to-miss "Guardar datos" button inside the
     * collapsed "Datos de la unidad" sidebar section to avoid losing
     * the edit. Returns JSON for that AJAX path, a redirect for the
     * classic full-page form submission (still works standalone).
     */
    public function update(Request $request, BusUnit $busUnit): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'has_upper_deck' => ['nullable', 'boolean'],
            'canvas_width' => ['required', 'integer', 'min:200'],
            'canvas_height' => ['required', 'integer', 'min:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $busUnit->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'has_upper_deck' => $validated['has_upper_deck'] ?? false,
            'canvas_width' => $validated['canvas_width'],
            'canvas_height' => $validated['canvas_height'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['busUnit' => $busUnit->fresh()]);
        }

        return redirect()->route('admin.unidades.edit', $busUnit)->with('success', 'Unidad actualizada.');
    }

    public function destroy(BusUnit $busUnit): RedirectResponse
    {
        $busUnit->delete();

        return redirect()->route('admin.unidades')->with('success', 'Unidad eliminada.');
    }
}
