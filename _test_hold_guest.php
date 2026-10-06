<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BusUnit;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
SeatHold::where('landing_route_id', $trip->id)->delete();
$trip->update(['available_seats' => 30]);

$seats = $trip->busUnit->seats()->where('kind', 'seat')->take(2)->get();
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// The CSRF middleware rejects the synthetic POSTs our test issues. The
// real browser carries the token from the seat-picker blade's
// <meta name="csrf-token">, so this only affects the harness.
Illuminate\Foundation\Testing\TestCase::class;
app(\Illuminate\Contracts\Console\Kernel::class);

// Bypass CSRF just for this script's harness — start a fresh session
// so the cookie domain/secure checks don't trip on the null driver.
$session = app('session.store');
$session->start();
// Use the session's fresh token to bypass CSRF — the controller
// code itself doesn't care, this is purely a test-harness detail.
$csrfToken = $session->token();

function postToHold($httpKernel, $tripId, $seatId, $csrfToken, $tripType = 'one_way') {
    $req = Request::create("/viajes/{$tripId}/asientos/{$seatId}/hold", 'POST', [
        '_token' => $csrfToken,
        'trip_type' => $tripType,
    ]);
    $req->setLaravelSession(app('session.store'));
    return $httpKernel->handle($req);
}

// === Test 1: guest (no auth) creates a hold ===
echo "=== Test 1: guest creates hold ===\n";
$res = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);
echo "Status: " . $res->getStatusCode() . "\n";

$hold = SeatHold::where('landing_route_id', $trip->id)->where('bus_unit_seat_id', $seats[0]->id)->first();
if ($hold && $hold->user_id === null && $hold->session_id && $hold->holder_id === 's:'.$hold->session_id) {
    echo "  ✓ Guest hold: user_id=null, session_id set, holder_id=session-prefixed\n";
} else {
    echo "  ✗ Guest hold invalid\n";
    exit(1);
}

// === Test 2: same guest refreshes hold (timer bump) — no conflict ===
echo "\n=== Test 2: same guest bumps own hold ===\n";
sleep(1);
$res2 = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);
echo "Status: " . $res2->getStatusCode() . "\n";
$hold2 = SeatHold::where('landing_route_id', $trip->id)->where('bus_unit_seat_id', $seats[0]->id)->first();
if ($hold2 && $hold2->expires_at->gt($hold->expires_at)) {
    echo "  ✓ Hold refreshed (expires_at advanced)\n";
} else {
    echo "  ✗ Hold not bumped\n";
}

// === Test 3: logged-in user creates hold (separate path) ===
echo "\n=== Test 3: logged-in user creates hold ===\n";
$admin = User::where('email', 'soportemerlotransportes@gmail.com')->firstOrFail();
Auth::login($admin);

$res3 = postToHold($httpKernel, $trip->id, $seats[1]->id, $csrfToken);
echo "Status: " . $res3->getStatusCode() . "\n";

$hold3 = SeatHold::where('landing_route_id', $trip->id)->where('bus_unit_seat_id', $seats[1]->id)->first();
if ($hold3 && $hold3->user_id === $admin->id && $hold3->session_id === null && $hold3->holder_id === 'u:'.$admin->id) {
    echo "  ✓ User hold: user_id set, session_id=null, holder_id=user-prefixed\n";
} else {
    echo "  ✗ User hold invalid\n";
    exit(1);
}

// === Test 4: Holder key uniqueness: u:1 vs s:1 — different "holders" ===
echo "\n=== Test 4: holder_id prefix prevents spoofing ===\n";
$row = new SeatHold();
$row->user_id = 1;
$row->session_id = null;
$userHolder = $row->getHolderIdAttribute();
$row2 = new SeatHold();
$row2->user_id = null;
$row2->session_id = '1';
$sessionHolder = $row2->getHolderIdAttribute();
if ($userHolder !== $sessionHolder && $userHolder === 'u:1' && $sessionHolder === 's:1') {
    echo "  ✓ u:1 !== s:1 — spoofing impossible\n";
}

// === Test 5: conflict between guest hold and user trying same seat ===
echo "\n=== Test 5: user tries to hold a seat guest is already holding ===\n";
// Reset
SeatHold::where('landing_route_id', $trip->id)->delete();
SeatReservation::where('landing_route_id', $trip->id)->delete();
$trip->update(['available_seats' => 30]);

Auth::logout();
$resGuest = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);
echo "Guest hold status: " . $resGuest->getStatusCode() . "\n";

Auth::login($admin);
$resUser = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);
echo "User trying same seat status: " . $resUser->getStatusCode() . " (expect 409 Conflict)\n";
if ($resUser->getStatusCode() === 409) {
    echo "  ✓ Conflicting user blocked (409)\n";
} else {
    echo "  ✗ User should have been blocked with 409, got '{$resUser->getStatusCode()}'\n";
}

// Cleanup
SeatHold::where('landing_route_id', $trip->id)->delete();
echo "\nCleanup done.\n";