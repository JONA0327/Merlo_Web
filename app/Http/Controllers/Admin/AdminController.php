<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\LandingRoute;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard');
    }

    public function viajes(): View
    {
        return view('admin.viajes', [
            'routes' => LandingRoute::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'busUnits' => BusUnit::where('is_active', true)
                ->withCount(['seats as bookable_seats_count' => fn ($query) => $query->bookable()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function ventas(): View
    {
        return view('admin.ventas');
    }
}
