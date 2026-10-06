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
use Illuminate\Support\Facades\Auth;

echo "=== Setup ===\n";
$admin = User::where('email', 'soportemerlotransportes@gmail.com')->firstOrFail();
Auth::login($admin);

// Ensure a trip exists
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
    ['price' => 650, 'is_active' => true]
);

SeatReservation::where('landing_route_id', $trip->id)->delete();
$trip->update(['available_seats' => 30]);

// Create guest reservations
$names = [
    ['Juan Pérez', '4441234567'],
    ['María López', '4449876543'],
];
$createdIds = [];
$seats = $trip->busUnit->seats()->where('kind', 'seat')->take(2)->get();
foreach ($names as $i => [$name, $phone]) {
    $r = SeatReservation::create([
        'landing_route_id' => $trip->id,
        'bus_unit_seat_id' => $seats[$i]->id,
        'user_id' => null,
        'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
        'leg' => SeatReservation::LEG_OUTBOUND,
        'unit_price' => 650,
        'payment_method' => SeatReservation::PAYMENT_METHOD_CASH,
        'payment_status' => SeatReservation::PAYMENT_PENDING,
        'paid_at' => null,
        'customer_name' => $name,
        'customer_email' => null,
        'customer_phone' => $phone,
        'status' => SeatReservation::STATUS_PENDING,
    ]);
    $createdIds[] = $r->id;
}

echo "Created reservations: " . implode(', ', $createdIds) . "\n";

echo "\n=== Test 1: GET /mis-boletos (form) ===\n";
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Illuminate\Http\Request::create('/mis-boletos', 'GET');
$res = $httpKernel->handle($req);
$html = $res->getContent();
if (str_contains($html, 'Encuentra tus boletos')) {
    echo "  ✓ Form page renders\n";
} else {
    echo "  ✗ Form page broken (status " . $res->getStatusCode() . ")\n";
}

echo "\n=== Test 2: POST with correct name+phone ===\n";
$req = Illuminate\Http\Request::create('/mis-boletos', 'POST', [
    '_token' => csrf_token(),
    'customer_name' => 'Juan Pérez',
    'customer_phone' => '444-123-4567',  // punctuation is normalized
]);
$req->setLaravelSession(app('session.store'));
$res = $httpKernel->handle($req);
if (str_contains($res->getContent(), 'Juan Pérez') && str_contains($res->getContent(), 'San Luis Potosí') || str_contains($res->getContent(), 'SLP')) {
    echo "  ✓ Found Juan's reservation (display name + trip visible)\n";
}
$content = $res->getContent();
if (str_contains($content, '$650')) {
    echo "  ✓ Shows the ticket amount\n";
}
if (str_contains($content, 'Ver boleto')) {
    echo "  ✓ Has 'Ver boleto' link\n";
}

echo "\n=== Test 3: POST with wrong phone ===\n";
$req = Illuminate\Http\Request::create('/mis-boletos', 'POST', [
    '_token' => csrf_token(),
    'customer_name' => 'Juan Pérez',
    'customer_phone' => '555-555-5555',
]);
$req->setLaravelSession(app('session.store'));
$res = $httpKernel->handle($req);
if (str_contains($res->getContent(), 'No encontramos boletos')) {
    echo "  ✓ Wrong phone → no reservations found\n";
}

echo "\n=== Test 4: POST with wrong name ===\n";
$req = Illuminate\Http\Request::create('/mis-boletos', 'POST', [
    '_token' => csrf_token(),
    'customer_name' => 'Fake Name',
    'customer_phone' => '4441234567',
]);
$req->setLaravelSession(app('session.store'));
$res = $httpKernel->handle($req);
if (str_contains($res->getContent(), 'No encontramos boletos')) {
    echo "  ✓ Wrong name → no reservations found\n";
}

echo "\n=== Test 5: Maria's lookup ===\n";
$req = Illuminate\Http\Request::create('/mis-boletos', 'POST', [
    '_token' => csrf_token(),
    'customer_name' => 'Maria Lopez',
    'customer_phone' => '444 987 6543',  // spaces
]);
$req->setLaravelSession(app('session.store'));
$res = $httpKernel->handle($req);
if (str_contains($res->getContent(), 'María López')) {
    echo "  ✓ Spaces + case-insensitive match works\n";
}

// Cleanup
SeatReservation::where('landing_route_id', $trip->id)->delete();
$trip->delete();
echo "\nCleanup done.\n";