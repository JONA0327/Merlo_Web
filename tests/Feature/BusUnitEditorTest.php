<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can upload a background image and enable the upper deck', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $busUnit = BusUnit::create(['name' => 'Autobús 1']);

    $response = $this->actingAs($admin)->put(route('admin.unidades.update', $busUnit), [
        'name' => 'Autobús 1',
        'description' => 'Doble piso',
        'canvas_width' => 800,
        'canvas_height' => 600,
        'has_upper_deck' => 1,
        'is_active' => 1,
        'background_image' => UploadedFile::fake()->image('plano.jpg'),
    ]);

    $response->assertRedirect(route('admin.unidades.edit', $busUnit));

    $busUnit->refresh();
    expect($busUnit->has_upper_deck)->toBeTrue();
    expect($busUnit->background_image)->not->toBeNull();
    Storage::disk('public')->assertExists($busUnit->background_image);
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
