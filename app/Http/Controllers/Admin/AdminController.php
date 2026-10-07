<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\LandingRoute;
use App\Models\Package;
use App\Models\SeatReservation;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'activeTripsCount' => LandingRoute::where('is_active', true)->where('day', '>=', today())->count(),
            'activePackagesCount' => Package::where('status', '!=', Package::STATUS_ENTREGADO)->count(),
            'monthlySales' => SeatReservation::where('payment_status', SeatReservation::PAYMENT_COMPLETED)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total'),
        ]);
    }

    public function viajes(): View
    {
        return view('admin.viajes', [
            'routes' => LandingRoute::query()
                ->with(['prices'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'busUnits' => BusUnit::where('is_active', true)
                ->withCount(['seats as bookable_seats_count' => fn ($query) => $query->bookable()])
                ->orderBy('name')
                ->get(),
            // Active destinations for the from/to <select> on the create
            // form. The edit form (admin.viajes-edit) passes its own list
            // through AdminLandingRouteController@edit, but it shares the
            // same <select> markup so both stay in lockstep.
            'destinations' => \App\Models\Destination::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function ventas(): View
    {
        return view('admin.ventas');
    }
}
