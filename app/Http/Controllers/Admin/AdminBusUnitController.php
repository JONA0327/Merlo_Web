<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'background_image' => ['nullable', 'image', 'max:4096'],
            'remove_background_image' => ['nullable', 'boolean'],
            'has_upper_deck' => ['nullable', 'boolean'],
            'canvas_width' => ['required', 'integer', 'min:200'],
            'canvas_height' => ['required', 'integer', 'min:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $backgroundImage = $busUnit->background_image;

        if ($request->hasFile('background_image')) {
            if ($backgroundImage) {
                Storage::disk('public')->delete($backgroundImage);
            }

            $backgroundImage = $request->file('background_image')->store('bus-units', 'public');
        } elseif ($request->boolean('remove_background_image') && $backgroundImage) {
            Storage::disk('public')->delete($backgroundImage);
            $backgroundImage = null;
        }

        $busUnit->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'background_image' => $backgroundImage,
            'has_upper_deck' => $validated['has_upper_deck'] ?? false,
            'canvas_width' => $validated['canvas_width'],
            'canvas_height' => $validated['canvas_height'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return redirect()->route('admin.unidades.edit', $busUnit)->with('success', 'Unidad actualizada.');
    }

    public function destroy(BusUnit $busUnit): RedirectResponse
    {
        if ($busUnit->background_image) {
            Storage::disk('public')->delete($busUnit->background_image);
        }

        $busUnit->delete();

        return redirect()->route('admin.unidades')->with('success', 'Unidad eliminada.');
    }
}
