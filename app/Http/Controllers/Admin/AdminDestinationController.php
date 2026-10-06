<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\LandingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDestinationController extends Controller
{
    /**
     * Master list of cities the company serves. The trip form's
     * `from` / `to` fields pull from this list so the admin doesn't
     * re-type "Ciudad de México" every time (and so customers never
     * see "ciudad de mexico" vs "CDMX" for the same place).
     */
    public function index(): View
    {
        $destinations = Destination::query()
            ->withCount('tripsAsOrigin as trips_from_count')
            ->withCount('tripsAsDestination as trips_to_count')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.destinations.index', [
            'destinations' => $destinations,
        ]);
    }

    public function create(): View
    {
        return view('admin.destinations.create', [
            'destination' => new Destination(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Destination::create($data);

        return redirect()
            ->route('admin.destinations.index')
            ->with('success', 'Destino agregado correctamente.');
    }

    public function edit(Destination $destination): View
    {
        return view('admin.destinations.edit', [
            'destination' => $destination,
        ]);
    }

    public function update(Request $request, Destination $destination): RedirectResponse
    {
        $data = $this->validated($request, $destination->id);

        $destination->update($data);

        return redirect()
            ->route('admin.destinations.index')
            ->with('success', 'Destino actualizado.');
    }

    public function destroy(Destination $destination): RedirectResponse
    {
        // Soft safety: even with is_active=false hiding it from new
        // trip forms, an operator might forget about a destination
        // that's still referenced by a past trip. Warn instead of
        // blocking — the destination already being invisible is enough.
        $inUse = $destination->tripsAsOrigin()->exists()
            || $destination->tripsAsDestination()->exists();
        if ($inUse && $destination->is_active) {
            return back()->with('error', 'Este destino está activo y aún hay viajes que lo usan. Desactívalo primero en lugar de eliminarlo.');
        }

        $destination->delete();

        return redirect()
            ->route('admin.destinations.index')
            ->with('success', 'Destino eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:destinations,name';
        if ($ignoreId !== null) {
            $uniqueRule .= ",{$ignoreId}";
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120', $uniqueRule],
            'code' => ['nullable', 'string', 'max:16'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'nombre',
            'code' => 'código',
            'is_active' => 'activo',
        ]) + ['is_active' => (bool) $request->input('is_active', false)];
    }
}