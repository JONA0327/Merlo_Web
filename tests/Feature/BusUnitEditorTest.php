<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\User;

test('admin can update a bus unit and enable the upper deck', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);

    $response = $this->actingAs($admin)->put(route('admin.unidades.update', $busUnit), [
        'name' => 'Autobús 1',
        'description' => 'Doble piso',
        'canvas_width' => 800,
        'canvas_height' => 600,
        'has_upper_deck' => 1,
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.unidades.edit', $busUnit));

    $busUnit->refresh();
    expect($busUnit->has_upper_deck)->toBeTrue();
    expect($busUnit->description)->toBe('Doble piso');
    expect($busUnit->is_active)->toBeTrue();
});

test('seats with the same label are allowed on different decks but not on the same deck', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1', 'has_upper_deck' => true]);

    $payload = [
        'seats' => [
            ['id' => null, 'label' => '1A', 'kind' => 'seat', 'type' => 'normal', 'deck' => 'lower', 'shape' => 'rect', 'width' => 40, 'height' => 40, 'corner_radius' => 8, 'border_width' => 2, 'pos_x' => 10, 'pos_y' => 10],
            ['id' => null, 'label' => '1A', 'kind' => 'seat', 'type' => 'normal', 'deck' => 'upper', 'shape' => 'rect', 'width' => 40, 'height' => 40, 'corner_radius' => 8, 'border_width' => 2, 'pos_x' => 10, 'pos_y' => 10],
        ],
    ];

    $response = $this->actingAs($admin)->putJson(route('admin.unidades.seats.sync', $busUnit), $payload);

    $response->assertOk();
    $this->assertDatabaseHas('bus_unit_seats', ['bus_unit_id' => $busUnit->id, 'deck' => 'lower', 'label' => '1A']);
    $this->assertDatabaseHas('bus_unit_seats', ['bus_unit_id' => $busUnit->id, 'deck' => 'upper', 'label' => '1A']);
    $this->assertDatabaseCount('bus_unit_seats', 2);
});

test('an object like a door can be saved alongside seats with custom style', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);

    $payload = [
        'seats' => [
            ['id' => null, 'label' => '1A', 'kind' => 'seat', 'type' => 'vip', 'deck' => 'lower', 'shape' => 'rect', 'width' => 40, 'height' => 40, 'corner_radius' => 20, 'border_width' => 4, 'pos_x' => 10, 'pos_y' => 10],
            ['id' => null, 'label' => 'PUERTA', 'kind' => 'object', 'type' => 'door', 'deck' => 'lower', 'shape' => 'rect', 'width' => 60, 'height' => 20, 'corner_radius' => 0, 'border_width' => 2, 'pos_x' => 60, 'pos_y' => 10],
        ],
    ];

    $response = $this->actingAs($admin)->putJson(route('admin.unidades.seats.sync', $busUnit), $payload);

    $response->assertOk();
    $this->assertDatabaseHas('bus_unit_seats', [
        'bus_unit_id' => $busUnit->id, 'label' => '1A', 'kind' => 'seat', 'type' => 'vip', 'corner_radius' => 20, 'border_width' => 4,
    ]);
    $this->assertDatabaseHas('bus_unit_seats', [
        'bus_unit_id' => $busUnit->id, 'label' => 'PUERTA', 'kind' => 'object', 'type' => 'door', 'width' => 60, 'height' => 20,
    ]);
});

test('a shape can be a resizable circle', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);

    $payload = [
        'seats' => [
            ['id' => null, 'label' => 'MESA', 'kind' => 'object', 'type' => 'table', 'deck' => 'lower', 'shape' => 'circle', 'width' => 80, 'height' => 80, 'corner_radius' => 8, 'border_width' => 2, 'pos_x' => 30, 'pos_y' => 30],
        ],
    ];

    $response = $this->actingAs($admin)->putJson(route('admin.unidades.seats.sync', $busUnit), $payload);

    $response->assertOk();
    $this->assertDatabaseHas('bus_unit_seats', [
        'bus_unit_id' => $busUnit->id, 'label' => 'MESA', 'shape' => 'circle', 'width' => 80, 'height' => 80,
    ]);
});

test('an outline object can be saved large with a custom color to draw the bus silhouette', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);

    $payload = [
        'seats' => [
            ['id' => null, 'label' => 'CONTORNO', 'kind' => 'object', 'type' => 'outline', 'deck' => 'lower', 'shape' => 'rect', 'width' => 740, 'height' => 540, 'corner_radius' => 40, 'border_width' => 4, 'color' => '#8C1D2B', 'pos_x' => 10, 'pos_y' => 10],
        ],
    ];

    $response = $this->actingAs($admin)->putJson(route('admin.unidades.seats.sync', $busUnit), $payload);

    $response->assertOk();
    $this->assertDatabaseHas('bus_unit_seats', [
        'bus_unit_id' => $busUnit->id, 'label' => 'CONTORNO', 'kind' => 'object', 'type' => 'outline', 'width' => 740, 'height' => 540, 'color' => '#8C1D2B',
    ]);
});

test('objects and disabled seats cannot be reserved by a customer', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);
    $door = BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => 'PUERTA', 'kind' => 'object', 'type' => 'door', 'pos_x' => 0, 'pos_y' => 0]);
    $disabledSeat = BusUnitSeat::create(['bus_unit_id' => $busUnit->id, 'label' => '1A', 'kind' => 'seat', 'type' => 'disabled', 'pos_x' => 0, 'pos_y' => 0]);
    $trip = LandingRoute::create([
        'from' => 'CDMX', 'to' => 'GDL', 'duration' => '1h', 'price' => '100',
        'available_seats' => 5, 'bus_unit_id' => $busUnit->id, 'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('travel.seats.store', $trip), ['seat_ids' => [$door->id]])
        ->assertRedirect(route('travel.seats', $trip));
    $this->actingAs($user)->post(route('travel.seats.store', $trip), ['seat_ids' => [$disabledSeat->id]])
        ->assertRedirect(route('travel.seats', $trip));

    $this->assertDatabaseCount('seat_reservations', 0);
});

test('admin can duplicate a bus unit into a new one with its own seat map', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create([
        'name' => 'Autobús 1',
        'description' => 'Doble piso',
        'has_upper_deck' => true,
        'canvas_width' => 900,
        'canvas_height' => 650,
        'is_active' => true,
    ]);
    BusUnitSeat::create([
        'bus_unit_id' => $busUnit->id, 'label' => '1A', 'kind' => 'seat', 'type' => 'vip', 'deck' => 'lower',
        'allowed_trip_type' => 'round_trip', 'zone' => 'A', 'shape' => 'rect',
        'width' => 40, 'height' => 40, 'corner_radius' => 20, 'border_width' => 4, 'color' => '#111111',
        'pos_x' => 10, 'pos_y' => 10,
    ]);
    BusUnitSeat::create([
        'bus_unit_id' => $busUnit->id, 'label' => 'PUERTA', 'kind' => 'object', 'type' => 'door', 'deck' => 'lower',
        'shape' => 'rect', 'width' => 60, 'height' => 20, 'corner_radius' => 0, 'border_width' => 2,
        'pos_x' => 60, 'pos_y' => 10,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.unidades.duplicate', $busUnit), [
        'name' => 'Autobús 1 (copia)',
    ]);

    $copy = BusUnit::where('name', 'Autobús 1 (copia)')->first();
    expect($copy)->not->toBeNull();
    $response->assertRedirect(route('admin.unidades.edit', $copy));

    expect($copy->description)->toBe('Doble piso');
    expect($copy->has_upper_deck)->toBeTrue();
    expect($copy->canvas_width)->toBe(900);
    expect($copy->canvas_height)->toBe(650);
    // Starts inactive regardless of the source unit's state, until
    // reviewed in the editor.
    expect($copy->is_active)->toBeFalse();

    $this->assertDatabaseCount('bus_unit_seats', 4);
    $this->assertDatabaseHas('bus_unit_seats', [
        'bus_unit_id' => $copy->id, 'label' => '1A', 'kind' => 'seat', 'type' => 'vip', 'deck' => 'lower',
        'allowed_trip_type' => 'round_trip', 'zone' => 'A', 'corner_radius' => 20, 'border_width' => 4, 'color' => '#111111',
    ]);
    $this->assertDatabaseHas('bus_unit_seats', [
        'bus_unit_id' => $copy->id, 'label' => 'PUERTA', 'kind' => 'object', 'type' => 'door', 'width' => 60, 'height' => 20,
    ]);

    // The original unit is untouched.
    expect($busUnit->seats()->count())->toBe(2);
});

test('admin can activate an inactive bus unit so it shows up as a plantilla choice', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1 (copia)', 'is_active' => false]);

    $response = $this->actingAs($admin)->patch(route('admin.unidades.toggle-active', $busUnit));

    $response->assertRedirect(route('admin.unidades'));
    expect($busUnit->refresh()->is_active)->toBeTrue();

    // Toggling again turns it back off.
    $this->actingAs($admin)->patch(route('admin.unidades.toggle-active', $busUnit));
    expect($busUnit->refresh()->is_active)->toBeFalse();
});
