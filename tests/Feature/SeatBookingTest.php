<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use App\Models\User;

test('a seat cannot be booked twice on the same trip', function () {
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);
    $seat = BusUnitSeat::create([
        'bus_unit_id' => $busUnit->id,
        'label' => '1A',
        'type' => 'normal',
        'pos_x' => 0,
        'pos_y' => 0,
    ]);

    $trip = LandingRoute::create([
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'price' => '$650',
        'available_seats' => 1,
        'bus_unit_id' => $busUnit->id,
        'is_active' => true,
    ]);

    $firstUser = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($firstUser)->post(route('travel.seats.store', $trip), [
        'seat_ids' => [$seat->id],
    ]);

    $response->assertRedirect(route('cliente.boletos'));
    $this->assertDatabaseCount('seat_reservations', 1);
    expect($trip->fresh()->available_seats)->toBe(0);

    $secondUser = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($secondUser)->post(route('travel.seats.store', $trip), [
        'seat_ids' => [$seat->id],
    ]);

    $response->assertRedirect(route('travel.seats', $trip));
    $this->assertDatabaseCount('seat_reservations', 1);
});

test('a seat currently held by another user cannot be purchased', function () {
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);
    $seat = BusUnitSeat::create([
        'bus_unit_id' => $busUnit->id,
        'label' => '1A',
        'type' => 'normal',
        'pos_x' => 0,
        'pos_y' => 0,
    ]);
    $trip = LandingRoute::create([
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'price' => '$650',
        'available_seats' => 1,
        'bus_unit_id' => $busUnit->id,
        'is_active' => true,
    ]);

    $holder = User::factory()->create(['email_verified_at' => now()]);
    SeatHold::create([
        'landing_route_id' => $trip->id,
        'bus_unit_seat_id' => $seat->id,
        'user_id' => $holder->id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $buyer = User::factory()->create(['email_verified_at' => now()]);
    $response = $this->actingAs($buyer)->post(route('travel.seats.store', $trip), [
        'seat_ids' => [$seat->id],
    ]);

    $response->assertRedirect(route('travel.seats', $trip));
    $this->assertDatabaseCount('seat_reservations', 0);
});

test('purchasing a held seat deletes its seat_holds row in the same transaction', function () {
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);
    $seat = BusUnitSeat::create([
        'bus_unit_id' => $busUnit->id,
        'label' => '1A',
        'type' => 'normal',
        'pos_x' => 0,
        'pos_y' => 0,
    ]);
    $trip = LandingRoute::create([
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'price' => '$650',
        'available_seats' => 1,
        'bus_unit_id' => $busUnit->id,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    SeatHold::create([
        'landing_route_id' => $trip->id,
        'bus_unit_seat_id' => $seat->id,
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->post(route('travel.seats.store', $trip), [
        'seat_ids' => [$seat->id],
    ]);

    $response->assertRedirect(route('cliente.boletos'));
    $this->assertDatabaseCount('seat_reservations', 1);
    $this->assertDatabaseCount('seat_holds', 0);
});

test('trips without an assigned bus unit still show the simple seat dropdown', function () {
    LandingRoute::create([
        'from' => 'Monterrey',
        'to' => 'Saltillo',
        'duration' => '1h 30m',
        'price' => '$150',
        'available_seats' => 10,
        'is_active' => true,
    ]);

    $this->get(route('travel.search', ['from' => 'Monterrey', 'to' => 'Saltillo']))
        ->assertOk()
        ->assertSee('Seleccionar')
        ->assertDontSee('Elegir asientos');
});
