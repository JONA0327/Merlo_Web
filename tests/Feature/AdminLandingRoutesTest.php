<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\User;

test('super admin can create travel routes and they show on the public landing page', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.viajes.store'), [
            'from' => 'Ciudad de México',
            'to' => 'Guadalajara',
            'duration' => '6h 30m',
            'price' => '$650',
            'is_active' => true,
            'featured' => true,
            'sort_order' => 1,
        ]);

    $response->assertRedirect(route('admin.viajes'));

    $this->assertDatabaseHas('landing_routes', [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'price' => '$650',
        'is_active' => true,
        'featured' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Ciudad de México')
        ->assertSee('Guadalajara')
        ->assertSee('$650');
});

test('a trip with a bus unit gets its available seats from the seat map, not the typed number', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);
    BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => '1A', 'kind' => 'seat', 'type' => 'normal', 'pos_x' => 0, 'pos_y' => 0]);
    BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => '1B', 'kind' => 'seat', 'type' => 'normal', 'pos_x' => 40, 'pos_y' => 0]);
    BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => '1C', 'kind' => 'seat', 'type' => 'disabled', 'pos_x' => 80, 'pos_y' => 0]);
    BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => 'PUERTA', 'kind' => 'object', 'type' => 'door', 'pos_x' => 120, 'pos_y' => 0]);

    $response = $this->actingAs($admin)->post(route('admin.viajes.store'), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'price' => '$650',
        'bus_unit_id' => $busUnit->id,
        'available_seats' => 999,
    ]);

    $response->assertRedirect(route('admin.viajes'));
    $this->assertDatabaseHas('landing_routes', ['bus_unit_id' => $busUnit->id, 'available_seats' => 2]);
});

test('updating a seat-mapped trip recomputes available seats minus what is already reserved', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);
    $seat1 = BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => '1A', 'kind' => 'seat', 'type' => 'normal', 'pos_x' => 0, 'pos_y' => 0]);
    BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => '1B', 'kind' => 'seat', 'type' => 'normal', 'pos_x' => 40, 'pos_y' => 0]);
    $trip = LandingRoute::create([
        'from' => 'Ciudad de México', 'to' => 'Guadalajara', 'duration' => '6h 30m', 'price' => '$650',
        'available_seats' => 2, 'bus_unit_id' => $busUnit->id, 'is_active' => true,
    ]);
    $buyer = User::factory()->create(['email_verified_at' => now()]);
    SeatReservation::create(['landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seat1->id, 'user_id' => $buyer->id]);

    $response = $this->actingAs($admin)->put(route('admin.viajes.update', $trip), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'price' => '$650',
        'bus_unit_id' => $busUnit->id,
        'available_seats' => 0,
    ]);

    $response->assertRedirect(route('admin.viajes'));
    $this->assertDatabaseHas('landing_routes', ['id' => $trip->id, 'available_seats' => 1]);
});
