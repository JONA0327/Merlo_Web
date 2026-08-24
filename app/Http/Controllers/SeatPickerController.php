<?php

namespace App\Http\Controllers;

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use App\Models\SeatReservation;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SeatPickerController extends Controller
{
    public function show(LandingRoute $landingRoute): View
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $landingRoute->load('busUnit.seats');

        return view('seat-picker', [
            'trip' => $landingRoute,
            'takenIds' => $landingRoute->seatReservations()->pluck('bus_unit_seat_id'),
            'heldSeats' => $landingRoute->seatHolds()->active()->get(['bus_unit_seat_id', 'user_id', 'expires_at']),
        ]);
    }

    public function store(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $validated = $request->validate([
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                'exists:bus_unit_seats,id',
            ],
        ]);

        $seatIds = collect($validated['seat_ids'])->unique()->values();

        try {
            DB::transaction(function () use ($landingRoute, $seatIds) {
                $trip = LandingRoute::query()->lockForUpdate()->findOrFail($landingRoute->id);

                $requestedSeats = $trip->busUnit->seats()->whereKey($seatIds)->get();

                abort_if($requestedSeats->count() !== $seatIds->count(), 422, 'Uno de los asientos seleccionados no pertenece a esta unidad.');

                abort_if(
                    $requestedSeats->contains(fn (BusUnitSeat $seat) => ! $seat->isBookable()),
                    422,
                    'Uno de los asientos seleccionados no está disponible para reservar.'
                );

                $alreadyTaken = $trip->seatReservations()->whereIn('bus_unit_seat_id', $seatIds)->exists();

                abort_if($alreadyTaken, 409, 'Uno de los asientos seleccionados ya fue reservado.');

                $heldByOthers = SeatHold::where('landing_route_id', $trip->id)
                    ->whereIn('bus_unit_seat_id', $seatIds)
                    ->where('user_id', '!=', auth()->id())
                    ->where('expires_at', '>', now())
                    ->exists();

                abort_if($heldByOthers, 409, 'Uno de los asientos seleccionados está siendo elegido por otra persona.');

                abort_if($seatIds->count() > $trip->available_seats, 422, 'No hay suficientes asientos disponibles.');

                foreach ($seatIds as $seatId) {
                    SeatReservation::create([
                        'landing_route_id' => $trip->id,
                        'bus_unit_seat_id' => $seatId,
                        'user_id' => auth()->id(),
                    ]);
                }

                $trip->decrement('available_seats', $seatIds->count());

                SeatHold::where('landing_route_id', $trip->id)->whereIn('bus_unit_seat_id', $seatIds)->delete();
            });
        } catch (HttpException|QueryException $e) {
            return redirect()->route('travel.seats', $landingRoute)
                ->with('error', 'Uno de los asientos seleccionados ya no está disponible. Elige otro.');
        }

        SeatAvailabilityUpdated::dispatchSafely(
            $landingRoute->id,
            // seat_ids arrives from a plain form POST, so these are strings —
            // cast to int so the JSON payload matches the number keys the
            // frontend's Map uses to look up each seat (a strict "168" !== 168
            // would otherwise silently drop this update).
            $seatIds->map(fn ($seatId) => ['id' => (int) $seatId, 'status' => 'purchased'])->all()
        );

        return redirect()->route('cliente.boletos')->with('success', '¡Asientos reservados!');
    }
}
