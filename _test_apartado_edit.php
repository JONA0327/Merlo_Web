<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BusUnit;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Models\User;
use Illuminate\Http\Request;

$admin = User::where('email', 'soportemerlotransportes@gmail.com')->firstOrFail();
\Auth::login($admin);

$bus = BusUnit::firstOrFail();
$trip = LandingRoute::whereDate('day', today())->first();
if (!$trip) {
    $trip = LandingRoute::create([
        'from' => 'CDMX', 'to' => 'SLP', 'day' => today()->toDateString(),
        'departure_time' => '08:00', 'available_seats' => 30,
        'bus_unit_id' => $bus->id, 'is_active' => true,
    ]);
}
TripTicketPrice::firstOrCreate(
    ['landing_route_id' => $trip->id, 'trip_type' => TripTicketPrice::TYPE_ONE_WAY],
    ['price' => 1100, 'is_active' => true]
);

SeatReservation::where('landing_route_id', $trip->id)->delete();
$trip->update(['available_seats' => 30]);

// Create a cash-pending apartado
$seat = $trip->busUnit->seats()->where('kind', 'seat')->first();
$ap = SeatReservation::create([
    'landing_route_id' => $trip->id,
    'bus_unit_seat_id' => $seat->id,
    'user_id' => null,
    'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
    'leg' => SeatReservation::LEG_OUTBOUND,
    'unit_price' => 1100,
    'payment_method' => SeatReservation::PAYMENT_METHOD_CASH,
    'payment_status' => SeatReservation::PAYMENT_PENDING,
    'paid_at' => null,
    'subtotal' => 1100,
    'tax' => 0,
    'total' => 1100,
    'currency' => 'MXN',
    'customer_name' => 'Beatriz HDZ',
    'customer_email' => 'merloturistica@hotmail.com',
    'customer_phone' => '444111222333',
    'status' => SeatReservation::STATUS_PENDING,
]);
echo "Apartado #{$ap->id}: payment_status={$ap->payment_status} paid_at=" . ($ap->paid_at ?: 'null') . "\n";

// Hit the controller's updateCategory (now accepts payment fields)
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Request::create("/admin/asientos/{$trip->id}/reservas/{$ap->id}/categoria", 'PUT', [
    '_token' => csrf_token(),
    'trip_type' => 'round_trip',
    'payment_method' => 'cash',
    'payment_status' => 'completed',
]);
$req->setLaravelSession(app('session.store'));
$res = $httpKernel->handle($req);
echo "Status: " . $res->getStatusCode() . "\n";

// Reload and verify
$ap->refresh();
echo "After update:\n";
echo "  trip_type: {$ap->trip_type}\n";
echo "  payment_method: {$ap->payment_method}\n";
echo "  payment_status: {$ap->payment_status}\n";
echo "  paid_at: " . ($ap->paid_at ?: 'null') . "\n";

// Now flip back to pending — paid_at should clear
$req2 = Request::create("/admin/asientos/{$trip->id}/reservas/{$ap->id}/categoria", 'PUT', [
    '_token' => csrf_token(),
    'trip_type' => 'round_trip',
    'payment_method' => 'cash',
    'payment_status' => 'pending',
]);
$req2->setLaravelSession(app('session.store'));
$httpKernel->handle($req2);

$ap->refresh();
echo "\nAfter flipping back to pending:\n";
echo "  payment_status: {$ap->payment_status}\n";
echo "  paid_at: " . ($ap->paid_at ?: 'null') . "\n";

// Cleanup
SeatReservation::where('landing_route_id', $trip->id)->delete();
echo "\nCleanup done.\n";