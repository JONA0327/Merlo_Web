<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripGuide;

function apartadoTrip(string $label, array $labels): array
{
    $bus = BusUnit::create(['name' => 'Bus '.$label]);
    $seats = collect($labels)->map(fn ($l, $i) => BusUnitSeat::create([
        'bus_unit_id' => $bus->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0,
    ]));
    $trip = LandingRoute::create([
        'from' => 'A', 'to' => 'B', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $bus->id, 'is_active' => true, 'day' => now()->addDay(),
    ]);

    return [$trip, $seats];
}

function apartadoRow(LandingRoute $trip, BusUnitSeat $seat, array $extra = []): SeatReservation
{
    return SeatReservation::create($extra + [
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seat->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'pending', 'payment_method' => 'cash', 'payment_status' => 'pending',
    ]);
}

test('seats paid differently inside one apartado split into their own groups', function () {
    [$trip, $seats] = apartadoTrip('1', ['1', '2', '3']);
    $root = apartadoRow($trip, $seats[0], ['payment_method' => 'transfer']);
    $b = apartadoRow($trip, $seats[1], ['notes' => 'group:'.$root->id]);
    $c = apartadoRow($trip, $seats[2], ['notes' => 'group:'.$root->id]);

    $roots = $root->regroupByPayment();

    expect($roots)->toHaveCount(2);
    expect($root->fresh()->groupMembers()->pluck('id')->all())->toBe([$root->id]);
    expect($b->fresh()->groupMembers()->pluck('id')->all())->toBe([$b->id, $c->id]);
    expect($root->fresh()->transfer_reference)->not->toBeNull();
});

test('confirming one payment group never marks the other method as paid', function () {
    [$trip, $seats] = apartadoTrip('2', ['1', '2']);
    $root = apartadoRow($trip, $seats[0], ['payment_method' => 'transfer']);
    $cash = apartadoRow($trip, $seats[1], ['notes' => 'group:'.$root->id]);

    $root->markGroupPaid();

    expect($root->fresh()->payment_status)->toBe('completed');
    expect($cash->fresh()->payment_status)->toBe('pending');
});

test('changing a trip plantilla keeps same-label seats', function () {
    [$trip, $oldSeats] = apartadoTrip('old', ['1', '2', '64']);
    $newBus = BusUnit::create(['name' => 'Bus new']);
    $new = collect(['1', '2', '54'])->map(fn ($l, $i) => BusUnitSeat::create([
        'bus_unit_id' => $newBus->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0,
    ]));

    $one = apartadoRow($trip, $oldSeats[0]);
    $two = apartadoRow($trip, $oldSeats[1]);
    $gone = apartadoRow($trip, $oldSeats[2]);

    $trip->update(['bus_unit_id' => $newBus->id]);
    $unmatched = TripGuide::remapTripReservations($trip->fresh());

    expect($unmatched)->toBe(['64']);
    expect($one->fresh()->bus_unit_seat_id)->toBe($new[0]->id);
    expect($two->fresh()->bus_unit_seat_id)->toBe($new[1]->id);
    expect($one->fresh()->hasSeatMismatch())->toBeFalse();
    expect($gone->fresh()->hasSeatMismatch())->toBeTrue();
});
