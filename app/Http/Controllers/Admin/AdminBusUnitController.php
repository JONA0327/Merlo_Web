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

    /**
     * Flips is_active from the units list — the only thing that decides
     * whether a unit shows up as a "plantilla" choice in Guías/viajes
     * (see e.g. AdminTripGuideController's own BusUnit::where('is_active',
     * true) lookups). A duplicate() copy starts inactive on purpose, so
     * this is how the admin turns it on once it's ready without having
     * to open the full editor form just to flip one checkbox.
     */
    public function toggleActive(BusUnit $busUnit): RedirectResponse
    {
        $busUnit->update(['is_active' => ! $busUnit->is_active]);

        return redirect()->route('admin.unidades')->with(
            'success',
            $busUnit->is_active
                ? "Unidad \"{$busUnit->name}\" activada — ya aparece para elegirla como plantilla en las guías."
                : "Unidad \"{$busUnit->name}\" desactivada."
        );
    }

    /**
     * Clones $busUnit's whole seat map (every seat, its position, shape,
     * zone, allowed_trip_type, etc.) into a brand-new unit under a
     * different name — for a second physical bus that's laid out the
     * same way, instead of rebuilding the distribution seat-by-seat in
     * the editor.
     */
    public function duplicate(Request $request, BusUnit $busUnit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $busUnit->load('seats');

        $copy = BusUnit::create([
            'name' => $validated['name'],
            'description' => $busUnit->description,
            'has_upper_deck' => $busUnit->has_upper_deck,
            'canvas_width' => $busUnit->canvas_width,
            'canvas_height' => $busUnit->canvas_height,
            // Starts inactive — kept out of the "choose a bus" lists for
            // new trips/plantillas until whoever made the copy has
            // checked the seat map and turned it on deliberately.
            'is_active' => false,
        ]);

        $copy->seats()->insert($busUnit->seats->map(fn ($seat) => [
            'bus_unit_id' => $copy->id,
            'deck' => $seat->deck,
            'kind' => $seat->kind,
            'allowed_trip_type' => $seat->allowed_trip_type,
            'zone' => $seat->zone,
            'label' => $seat->label,
            'type' => $seat->type,
            'shape' => $seat->shape,
            'width' => $seat->width,
            'height' => $seat->height,
            'corner_radius' => $seat->corner_radius,
            'border_width' => $seat->border_width,
            'color' => $seat->color,
            'pos_x' => $seat->pos_x,
            'pos_y' => $seat->pos_y,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        return redirect()->route('admin.unidades.edit', $copy)->with('success', "Unidad \"{$copy->name}\" creada a partir del mapa de \"{$busUnit->name}\".");
    }
}
