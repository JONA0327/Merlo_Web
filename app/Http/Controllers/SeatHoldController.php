<?php

namespace App\Http\Controllers;

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SeatHoldController extends Controller
{
    public function store(LandingRoute $landingRoute, BusUnitSeat $busUnitSeat): JsonResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $hold = DB::transaction(function () use ($landingRoute, $busUnitSeat) {
            $trip = LandingRoute::query()->lockForUpdate()->findOrFail($landingRoute->id);

            abort_unless(
                $busUnitSeat->bus_unit_id === $trip->bus_unit_id && $busUnitSeat->isBookable(),
                422,
                'Ese asiento no está disponible para reservar.'
            );

            abort_if(
                $trip->seatReservations()->where('bus_unit_seat_id', $busUnitSeat->id)->exists(),
                409,
                'Ese asiento ya fue comprado.'
            );

            $existingHold = SeatHold::where('landing_route_id', $trip->id)
                ->where('bus_unit_seat_id', $busUnitSeat->id)
                ->first();

            if ($existingHold && ! $existingHold->isExpired() && $existingHold->user_id !== auth()->id()) {
                abort(409, 'Ese asiento ya está siendo elegido por otra persona.');
            }

            return SeatHold::updateOrCreate(
                ['landing_route_id' => $trip->id, 'bus_unit_seat_id' => $busUnitSeat->id],
                ['user_id' => auth()->id(), 'expires_at' => now()->addMinutes(10)]
            );
        });

        SeatAvailabilityUpdated::dispatchSafely($landingRoute->id, [[
            'id' => $busUnitSeat->id,
            'status' => 'held',
            'heldBy' => auth()->id(),
            'expiresAt' => $hold->expires_at->toIso8601String(),
        ]]);

        return response()->json(['status' => 'held', 'expiresAt' => $hold->expires_at->toIso8601String()]);
    }

    public function destroy(LandingRoute $landingRoute, BusUnitSeat $busUnitSeat): JsonResponse
    {
        DB::transaction(function () use ($landingRoute, $busUnitSeat) {
            LandingRoute::query()->lockForUpdate()->findOrFail($landingRoute->id);

            SeatHold::where('landing_route_id', $landingRoute->id)
                ->where('bus_unit_seat_id', $busUnitSeat->id)
                ->where('user_id', auth()->id())
                ->delete();
        });

        SeatAvailabilityUpdated::dispatchSafely($landingRoute->id, [[
            'id' => $busUnitSeat->id,
            'status' => 'available',
        ]]);

        return response()->json(['status' => 'available']);
    }
}
