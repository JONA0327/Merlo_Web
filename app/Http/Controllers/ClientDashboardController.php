<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientDashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (auth()->user()->role === User::ROLE_PAQUETERIA) {
            return redirect()->route('admin.paqueteria');
        }

        return view('client.dashboard');
    }

    public function carrito(): View
    {
        return view('client.carrito');
    }

    public function compras(): View
    {
        return view('client.compras');
    }

    public function paquetes(): View
    {
        return view('client.paquetes');
    }

    public function boletos(): View
    {
        $reservations = auth()->user()->seatReservations()
            ->with(['landingRoute', 'seat'])
            ->latest()
            ->get()
            ->groupBy('landing_route_id');

        return view('client.boletos', [
            'reservations' => $reservations,
        ]);
    }
}
