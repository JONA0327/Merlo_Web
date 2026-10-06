<?php

namespace App\Http\Controllers;

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeatHoldController extends Controller
{
    public function store(Request $request, LandingRoute $landingRoute, BusUnitSeat $busUnitSeat): JsonResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $isReturnResale = $request->input('trip_type') === 'return_resale';
        $holder = $this->resolveHolder($request);

        $hold = DB::transaction(function () use ($landingRoute, $busUnitSeat, $isReturnResale, $holder) {
            $trip = LandingRoute::query()->lockForUpdate()->findOrFail($landingRoute->id);

            abort_unless(
                $busUnitSeat->bus_unit_id === $trip->bus_unit_id && $busUnitSeat->isBookable(),
                422,
                'Ese asiento no está disponible para reservar.'
            );

            $existingReservations = $trip->seatReservations()->where('bus_unit_seat_id', $busUnitSeat->id)->get();

            if ($isReturnResale) {
                // A resale hold only makes sense on a seat whose
                // round-trip reservation has its return leg released
                // and still within the resale window — everything
                // else (no reservation at all, already resold, window
                // closed) means there's nothing to hold here.
                $resaleOpen = $existingReservations->contains(fn ($r) => $r->isRoundTrip() && $r->isResaleWindowOpen());
                abort_unless($resaleOpen, 409, 'Ese asiento de regreso ya no está disponible.');
            } else {
                abort_if($existingReservations->isNotEmpty(), 409, 'Ese asiento ya fue comprado.');
            }

            $existingHold = SeatHold::where('landing_route_id', $trip->id)
                ->where('bus_unit_seat_id', $busUnitSeat->id)
                ->first();

            // Don't override an active hold owned by someone else.
            // (Our own hold just gets refreshed below — that's normal
            // when the user clicks a seat again to "bump" the timer.)
            if ($existingHold && ! $existingHold->isExpired() && $existingHold->holder_id !== $holder['key']) {
                abort(409, 'Ese asiento ya está siendo elegido por otra persona.');
            }

            // Stamp the holder on create/update so both logged-in
            // users (user_id) and guests (session_id) end up with the
            // right identifier for the SeatHold::holder_id accessor.
            $attrs = ['expires_at' => now()->addMinutes(10)];
            if ($holder['type'] === 'user') {
                $attrs['user_id'] = $holder['id'];
                $attrs['session_id'] = null;
            } else {
                $attrs['user_id'] = null;
                $attrs['session_id'] = $holder['id'];
            }

            return SeatHold::updateOrCreate(
                ['landing_route_id' => $trip->id, 'bus_unit_seat_id' => $busUnitSeat->id],
                $attrs
            );
        });

        // Broadcast carries the same "u:1" / "s:..." form so the JS
        // compareSelf logic doesn't need two separate fields.
        SeatAvailabilityUpdated::dispatchSafely($landingRoute->id, [[
            'id' => $busUnitSeat->id,
            'status' => 'held',
            'heldBy' => $holder['key'],
            'expiresAt' => $hold->expires_at->toIso8601String(),
        ]]);

        return response()->json([
            'status' => 'held',
            'expiresAt' => $hold->expires_at->toIso8601String(),
            'holderId' => $holder['key'],
        ]);
    }

    public function destroy(Request $request, LandingRoute $landingRoute, BusUnitSeat $busUnitSeat): JsonResponse
    {
        $holder = $this->resolveHolder($request);

        DB::transaction(function () use ($landingRoute, $busUnitSeat, $holder) {
            LandingRoute::query()->lockForUpdate()->findOrFail($landingRoute->id);

            // Only the row this visitor owns gets removed — anyone
            // else's hold is left alone so the seat stays held for
            // them for the rest of their window.
            $query = SeatHold::where('landing_route_id', $landingRoute->id)
                ->where('bus_unit_seat_id', $busUnitSeat->id);

            if ($holder['type'] === 'user') {
                $query->where('user_id', $holder['id']);
            } else {
                $query->where('session_id', $holder['id']);
            }
            $query->delete();
        });

        SeatAvailabilityUpdated::dispatchSafely($landingRoute->id, [[
            'id' => $busUnitSeat->id,
            'status' => 'available',
        ]]);

        return response()->json(['status' => 'available']);
    }

    /**
     * Resolve the visitor's "holder key" used for this controller's
     * row writes and for the broadcast payload. Logged-in users use
     * their account id; everyone else falls back to the Laravel
     * session id (which every browser already carries as a cookie).
     */
    private function resolveHolder(Request $request): array
    {
        if ($user = $request->user()) {
            return ['type' => 'user', 'id' => $user->id, 'key' => 'u:'.$user->id];
        }

        $sid = $request->session()->getId();

        return ['type' => 'session', 'id' => $sid, 'key' => 's:'.$sid];
    }
}
