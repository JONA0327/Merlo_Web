<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\BusUnitSeat;
use Illuminate\Support\Facades\DB;

// Find any trip with at least one free seat so we don't collide with
// the existing unique(landing_route_id, bus_unit_seat_id) index.
$trip = LandingRoute::query()
    ->where('is_active', true)
    ->whereHas('busUnit.seats')
    ->first();

if (! $trip) {
    echo "SKIP: no hay viajes activos con mapa de asientos." . PHP_EOL;
    exit(0);
}

$taken = $trip->seatReservations()->pluck('bus_unit_seat_id')->all();
$freeSeat = $trip->busUnit->seats()
    ->whereNotIn('id', $taken)
    ->where('kind', 'seat')
    ->first();

if (! $freeSeat) {
    echo "SKIP: no hay asientos libres en trip #{$trip->id}." . PHP_EOL;
    exit(0);
}

echo "Using trip #{$trip->id} seat #{$freeSeat->id} ({$freeSeat->label})" . PHP_EOL;

try {
    DB::transaction(function () use ($trip, $freeSeat) {
        $r = SeatReservation::create([
            'landing_route_id' => $trip->id,
            'bus_unit_seat_id' => $freeSeat->id,
            'user_id' => null,
            'trip_type' => 'one_way',
            'unit_price' => 600,
            'customer_name' => 'Smoke Test Apartado',
            'customer_email' => 'smoke+apartado@example.com',
            'status' => SeatReservation::STATUS_PENDING,
            'reserved_by' => 1,
            'notes' => 'smoke test',
        ]);
        echo "CREATED reservation #{$r->id} with user_id=" . var_export($r->user_id, true) . PHP_EOL;
        // Clean up so we don't pollute the dev DB.
        $r->delete();
        echo "CLEANED up smoke row." . PHP_EOL;
    });
} catch (Throwable $e) {
    echo "FAIL: " . get_class($e) . " — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "OK" . PHP_EOL;
