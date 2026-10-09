<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripGuide;
use App\Models\User;

function regresoTrip(): array
{
    $bus = BusUnit::create(['name' => 'Bus R']);
    $seats = collect(['33', '34', '35'])->map(fn ($l, $i) => BusUnitSeat::create([
        'bus_unit_id' => $bus->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0,
    ]));
    $trip = LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $bus->id, 'is_active' => true,
        'day' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
    ]);

    return [$trip, $seats];
}

function regresoRow(LandingRoute $trip, BusUnitSeat $seat, array $extra = []): SeatReservation
{
    return SeatReservation::create($extra + [
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seat->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'sent', 'payment_method' => 'cash', 'payment_status' => 'completed',
    ]);
}

function regresoAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
}

test('a solo-ida seat with no return ticket is open for a regreso', function () {
    [$trip, $seats] = regresoTrip();
    regresoRow($trip, $seats[0]);
    regresoRow($trip, $seats[1]);
    regresoRow($trip, $seats[1], ['trip_type' => 'regreso', 'leg' => 'return']);
    regresoRow($trip, $seats[2], ['trip_type' => 'round_trip']);

    expect(SeatReservation::idaOnlySeatIdsFor($trip)->all())->toBe([$seats[0]->id]);
});

test('a regreso on another day is staged in the guide for the trip that comes back that day', function () {
    [$trip, $seats] = regresoTrip();
    $returnDate = now()->addDays(5)->startOfDay();

    $this->actingAs(regresoAdmin())->post(route('admin.asientos.store', $trip), [
        'customer_name' => 'Luis', 'customer_phone' => '4441234567', 'trip_type' => 'regreso',
        'paid' => '0', 'payment_method' => [$seats[1]->id => 'cash'], 'seat_ids' => [$seats[1]->id],
        'regreso_date' => $returnDate->toDateString(),
    ])->assertRedirect()->assertSessionHas('success');

    $row = SeatReservation::where('customer_name', 'Luis')->sole();
    $guide = TripGuide::sole();
    expect($row->landing_route_id)->toBeNull();
    expect($row->trip_guide_id)->toBe($guide->id);
    expect($row->travel_date->toDateString())->toBe($returnDate->copy()->subDay()->toDateString());
    expect($row->leg)->toBe('return');
    expect($row->trip_type)->toBe('regreso');
    expect($row->bus_unit_seat_id)->toBe($seats[1]->id);
    expect($guide->from)->toBe('SLP');
});

test('editing a regreso to another day moves it off the trip into the guide', function () {
    [$trip, $seats] = regresoTrip();
    $root = regresoRow($trip, $seats[0], ['trip_type' => 'regreso', 'leg' => 'return', 'notes' => 'nota']);
    $other = regresoRow($trip, $seats[1], ['trip_type' => 'regreso', 'leg' => 'return', 'notes' => 'group:'.$root->id]);
    $returnDate = now()->addDays(4)->startOfDay();

    $this->actingAs(regresoAdmin())->put(route('admin.asientos.update-category', [$trip, $root]), [
        'trip_type' => 'regreso', 'payment_method' => 'cash', 'payment_status' => 'completed',
        'regreso_date' => $returnDate->toDateString(),
    ])->assertRedirect()->assertSessionHas('success');

    $root->refresh();
    expect($root->landing_route_id)->toBeNull();
    expect($root->travel_date->toDateString())->toBe($returnDate->copy()->subDay()->toDateString());
    expect($root->status)->toBe('pending');
    // The seat that stayed on the trip takes over the apartado.
    expect($other->fresh()->notes)->toBe('nota');
    expect($other->fresh()->landing_route_id)->toBe($trip->id);
});

test('a regreso on a day whose trip already exists is refused', function () {
    [$trip, $seats] = regresoTrip();
    LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $trip->bus_unit_id, 'is_active' => true,
        'day' => now()->addDays(3)->toDateString(), 'return_date' => now()->addDays(4)->toDateString(),
    ]);

    $this->actingAs(regresoAdmin())->post(route('admin.asientos.store', $trip), [
        'customer_name' => 'Luis', 'customer_phone' => '4441234567', 'trip_type' => 'regreso',
        'paid' => '0', 'payment_method' => [$seats[1]->id => 'cash'], 'seat_ids' => [$seats[1]->id],
        'regreso_date' => now()->addDays(4)->toDateString(),
    ])->assertRedirect()->assertSessionHas('error');

    expect(SeatReservation::where('customer_name', 'Luis')->exists())->toBeFalse();
});

test('a staged regreso links to the trip once it opens', function () {
    \Illuminate\Support\Facades\Queue::fake();
    [$trip, $seats] = regresoTrip();

    $this->actingAs(regresoAdmin())->post(route('admin.asientos.store', $trip), [
        'customer_name' => 'Luis', 'customer_phone' => '4441234567', 'trip_type' => 'regreso',
        'paid' => '1', 'payment_method' => [$seats[1]->id => 'cash'], 'seat_ids' => [$seats[1]->id],
        'regreso_date' => now()->addDays(6)->toDateString(),
    ])->assertSessionHas('success');

    $later = LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $trip->bus_unit_id, 'is_active' => true,
        'day' => now()->addDays(5)->toDateString(), 'return_date' => now()->addDays(6)->toDateString(),
    ]);
    TripGuide::linkTrip($later);

    $row = SeatReservation::where('customer_name', 'Luis')->sole();
    expect($row->landing_route_id)->toBe($later->id);
    expect($row->leg)->toBe('return');
});
