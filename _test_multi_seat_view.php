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

// Authenticate as the admin first so we can create the test data,
// but logout before hitting /pago/{id}/pendiente since the pending
// page's abort check expects $reservation->user_id === $request->user()?->id
// (both null for guests).
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

SeatReservation::where('landing_route_id', $trip->id)->delete();
$trip->update(['available_seats' => 30]);

// Simulate a 2-seat purchase (same shape SeatPickerController::store does)
$seats = $trip->busUnit->seats()->where('kind', 'seat')->take(2)->get();

$root = SeatReservation::create([
    'landing_route_id' => $trip->id,
    'bus_unit_seat_id' => $seats[0]->id,
    'user_id' => null,
    'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
    'leg' => SeatReservation::LEG_OUTBOUND,
    'unit_price' => 650,
    'payment_method' => SeatReservation::PAYMENT_METHOD_CASH,
    'payment_status' => SeatReservation::PAYMENT_PENDING,
    'subtotal' => 650,
    'tax' => 0,
    'total' => 1300,
    'currency' => 'MXN',
    'customer_name' => 'Jonathan Israel Loredo',
    'customer_email' => null,
    'customer_phone' => '4441234567',
    'status' => SeatReservation::STATUS_PENDING,
]);

$child = SeatReservation::create([
    'landing_route_id' => $trip->id,
    'bus_unit_seat_id' => $seats[1]->id,
    'user_id' => null,
    'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
    'leg' => SeatReservation::LEG_OUTBOUND,
    'unit_price' => 650,
    'payment_method' => SeatReservation::PAYMENT_METHOD_CASH,
    'payment_status' => SeatReservation::PAYMENT_PENDING,
    'subtotal' => 650,
    'tax' => 0,
    'total' => 650,
    'currency' => 'MXN',
    'customer_name' => 'Jonathan Israel Loredo',
    'customer_email' => null,
    'customer_phone' => '4441234567',
    'status' => SeatReservation::STATUS_PENDING,
    'notes' => 'group:'.$root->id,
]);

echo "Created root #{$root->id} + child #{$child->id} (notes={$child->notes})\n";

$group = $root->groupMembers();
echo "groupMembers(): " . $group->count() . " rows\n";
foreach ($group as $m) {
    echo "  - #{$m->id} seat={$m->seat?->label}\n";
}

// Render the pending view — must logout so the abort check passes
// (guest bookings have user_id=null, so the request must also be null).
\Auth::logout();

$controller = app(\App\Http\Controllers\SeatPickerController::class);
$req = Request::create("/pago/{$root->id}/pendiente", 'GET');
$req->setLaravelSession(app('session.store'));
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$res = $httpKernel->handle($req);
$html = $res->getContent();

echo "\n=== Pending view rendering ===\n";
echo "Status: " . $res->getStatusCode() . "\n";

// Check for both seats
// Final sanity: each unique seat label must appear at least once
// in the HTML. The cash-payment block also has to reflect the group
// total, not just one ticket's price (a previous copy paste per row
// disagreed by 100× in the test reservation).
foreach ($seats as $seat) {
    if (substr_count($html, '>' . $seat->label . '<') >= 1) {
        echo "  ✓ Seat {$seat->label} rendered\n";
    } else {
        echo "  ✗ Seat {$seat->label} missing\n";
    }
}
if (str_contains($html, '$1,300.00 MXN')) {
    echo "  ✓ Cash-payment block shows total $1,300.00 (sum of group)\n";
} else {
    echo "  ✗ Cash-payment block missing $1,300.00\n";
}

// Write the rendered HTML to a file so we can inspect it.
file_put_contents('_test_rendered.html', $html);
echo "\nFull HTML written to _test_rendered.html\n";

if (str_contains($html, '2 boletos')) {
    echo "  ✓ Shows '2 boletos' header\n";
}
if (str_contains($html, 'Boleto 1 de 2')) {
    echo "  ✓ Shows 'Boleto 1 de 2'\n";
}
if (str_contains($html, 'Boleto 2 de 2')) {
    echo "  ✓ Shows 'Boleto 2 de 2'\n";
}
if (str_contains($html, '$1,300.00')) {
    echo "  ✓ Shows total $1,300.00\n";
}

echo "\nCleanup...\n";
SeatReservation::where('landing_route_id', $trip->id)->delete();
$trip->delete();
echo "Done.\n";