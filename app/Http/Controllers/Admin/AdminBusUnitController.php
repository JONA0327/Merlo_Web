<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
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

    public function update(Request $request, BusUnit $busUnit): RedirectResponse
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

        return redirect()->route('admin.unidades.edit', $busUnit)->with('success', 'Unidad actualizada.');
    }

    public function destroy(BusUnit $busUnit): RedirectResponse
    {
        $busUnit->delete();

        return redirect()->route('admin.unidades')->with('success', 'Unidad eliminada.');
    }
}
