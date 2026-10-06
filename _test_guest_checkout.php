<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Http\Controllers\SeatPickerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=== Setup: ensure trip + bus unit + price exist ===\n";
$bus = BusUnit::first();
$trip = LandingRoute::whereDate('day', today())->first();
if (!$bus || !$trip) {
    echo "Need a bus unit + a trip for today. Run the seed test first.\n";
    exit(1);
}
$busUnitId = $bus->id;
$tripId = $trip->id;

$price = TripTicketPrice::where('landing_route_id', $tripId)
    ->where('trip_type', TripTicketPrice::TYPE_ONE_WAY)
    ->first();
if (!$price || (float) $price->price <= 0) {
    TripTicketPrice::create([
        'landing_route_id' => $tripId,
        'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
        'price' => 650,
        'is_active' => true,
    ]);
}

$seats = $bus->seats()->where('kind', 'seat')->where('type', '!=', 'disabled')->take(2)->get();
echo "Bus: {$bus->name} ({$seats->count()} bookable seats to test, ids: " . $seats->pluck('id')->implode(',') . ")\n";
echo "Trip: #{$tripId} (avail before: {$trip->fresh()->available_seats}, bus_unit_id: {$trip->bus_unit_id})\n";
echo "Trip's bus_unit seats total: " . $trip->busUnit->seats()->where('kind', 'seat')->where('type', '!=', 'disabled')->count() . "\n";

// Wipe any leftover test reservations
SeatReservation::where('landing_route_id', $tripId)->delete();
$trip->update(['available_seats' => 30]);

// Use seats that BELONG to this trip's bus unit
$seats = $trip->busUnit->seats()->where('kind', 'seat')->where('type', '!=', 'disabled')->take(2)->get();
echo "Re-fetched seats for trip's bus_unit: " . $seats->pluck('id')->implode(',') . "\n";

echo "\n=== Test: guest checkout marks seats as taken ===\n";

$initialCount = SeatReservation::where('landing_route_id', $tripId)->count();
$initialAvail = $trip->fresh()->available_seats;

$controller = app(SeatPickerController::class);
$request = Request::create("/viajes/{$tripId}/asientos", 'POST', [
    'trip_type' => 'one_way',
    'payment_method' => 'cash',
    'seat_ids' => [$seats[0]->id, $seats[1]->id],
    'customer_name' => 'Cliente Guest Test',
    'customer_phone' => '4441234567',
    '_token' => csrf_token(),
]);
// Spoof the actual CSRF token middleware
$request->setLaravelSession(app('session.store'));

try {
    $response = $controller->store($request, $trip, app(\App\Services\EvolutionWhatsAppService::class));
    echo "Controller status: " . $response->getStatusCode() . "\n";
    if ($response->isRedirect()) {
        echo "Redirect target: " . $response->getTargetUrl() . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$afterAvail = $trip->fresh()->available_seats;
$afterReservations = SeatReservation::where('landing_route_id', $tripId)->get();

echo "Reservations after: " . $afterReservations->count() . " (expect 2)\n";
echo "available_seats after: $afterAvail (was $initialAvail, expect $initialAvail - 2 = " . ($initialAvail - 2) . ")\n";

$guestReservations = $afterReservations->whereNull('user_id');
echo "Guest (user_id=null) reservations: " . $guestReservations->count() . " (expect 2)\n";

$first = $guestReservations->first();
if ($first) {
    echo "First reservation: name='{$first->customer_name}' phone='{$first->customer_phone}' email=" . ($first->customer_email ?: 'NULL') . " status={$first->status} payment_status={$first->payment_status}\n";
}

echo "\n=== Test: takenIds on show() reflects new reservations ===\n";
$showRequest = Request::create("/viajes/{$tripId}/asientos", 'GET');
$showController = app(SeatPickerController::class);
$showView = $showController->show($trip, $showRequest);
$takenIds = $showView->getData()['takenIds'];
echo "takenIds: " . $takenIds->implode(', ') . "\n";
echo "Contains seat[0] ({$seats[0]->id}): " . ($takenIds->contains($seats[0]->id) ? 'YES' : 'NO') . "\n";
echo "Contains seat[1] ({$seats[1]->id}): " . ($takenIds->contains($seats[1]->id) ? 'YES' : 'NO') . "\n";

// Cleanup
SeatReservation::where('landing_route_id', $tripId)->delete();
$trip->update(['available_seats' => 30]);
echo "\nCleanup done.\n";