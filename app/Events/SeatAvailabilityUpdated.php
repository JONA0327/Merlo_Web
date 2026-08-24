<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeatAvailabilityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array{id: int, status: string, heldBy?: int|null, expiresAt?: string|null}>  $seats
     */
    public function __construct(
        public int $landingRouteId,
        public array $seats,
    ) {}

    /**
     * The seat hold/purchase has already been committed to the database by
     * the time this fires — it's just telling other open tabs to repaint.
     * ShouldBroadcastNow means this runs synchronously inline with the HTTP
     * request, so if the broadcast server (Reverb) is unreachable, dispatch()
     * throws and would otherwise turn an already-successful booking into a
     * 500 response. Swallow that here: losing live cross-tab sync is fine,
     * losing the booking response is not.
     *
     * @param  array<int, array{id: int, status: string, heldBy?: int|null, expiresAt?: string|null}>  $seats
     */
    public static function dispatchSafely(int $landingRouteId, array $seats): void
    {
        try {
            self::dispatch($landingRouteId, $seats);
        } catch (Throwable $e) {
            Log::warning('Seat availability broadcast failed', [
                'landing_route_id' => $landingRouteId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('seat-availability.'.$this->landingRouteId)];
    }

    public function broadcastAs(): string
    {
        return 'seat.status.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'landingRouteId' => $this->landingRouteId,
            'seats' => $this->seats,
        ];
    }
}
