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

test('the hour and minute selects are stored as a 24h time', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.viajes.store'), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'departure_time_hour' => 14,
        'departure_time_minute' => 5,
    ]);

    $response->assertRedirect(route('admin.viajes'));
    $this->assertDatabaseHas('landing_routes', ['departure_time' => '14:05']);
});

test('updating a trip rewrites its 24h time from the selects', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $trip = LandingRoute::create([
        'from' => 'Ciudad de México', 'to' => 'Guadalajara', 'duration' => '6h 30m',
        'departure_time' => '09:00', 'available_seats' => 2, 'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.viajes.update', $trip), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'departure_time_hour' => 23,
        'departure_time_minute' => 45,
    ]);

    $response->assertRedirect(route('admin.viajes'));
    $this->assertDatabaseHas('landing_routes', ['id' => $trip->id, 'departure_time' => '23:45']);
});

test('every admin travel form label points at a real field id', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $trip = LandingRoute::create([
        'from' => 'Ciudad de México', 'to' => 'Guadalajara', 'duration' => '6h 30m',
        'available_seats' => 2, 'departure_time' => '14:07', 'is_active' => true,
    ]);

    foreach (['admin.viajes', 'admin.viajes-edit'] as $view) {
        $this->actingAs($admin);

        $html = view($view, [
            'route' => $trip,
            'routes' => LandingRoute::all(),
            'busUnits' => BusUnit::where('is_active', true)->withCount(['seats as bookable_seats_count' => fn ($query) => $query->bookable()])->get(),
        ])->with('errors', new \Illuminate\Support\ViewErrorBag)->render();

        preg_match_all('/<label for="([^"]+)"/', $html, $labels);
        preg_match_all('/<(?:input|select)[^>]*\bid="([^"]+)"/', $html, $ids);

        expect($labels[1])->not->toBeEmpty();

        foreach (array_diff($labels[1], $ids[1]) as $orphan) {
            expect($orphan)->toBeString();
            $this->fail("{$view}: el label for=\"{$orphan}\" no apunta a ningun id del formulario");
        }
    }
});

test('a trip can be saved without a duration', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.viajes.store'), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
    ]);

    $response->assertRedirect(route('admin.viajes'));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('landing_routes', ['from' => 'Ciudad de México', 'duration' => null]);
});

test('zero-padded hour and minute strings from the real <select> markup are accepted', function () {
    // The time-select component always renders zero-padded option values
    // ("00".."23" / "00".."55"), and a real browser submits <select>
    // values as plain strings — unlike $this->post()'s raw-PHP-int test
    // data above, which never exercises this path. "09"/"05" used to
    // fail validation because PHP's FILTER_VALIDATE_INT (what the
    // 'integer' rule uses) rejects leading-zero strings.
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.viajes.store'), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'departure_time_hour' => '09',
        'departure_time_minute' => '05',
    ]);

    $response->assertRedirect(route('admin.viajes'));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('landing_routes', ['departure_time' => '09:05']);
});

test('an out of range hour is rejected', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.viajes.store'), [
        'from' => 'Ciudad de México',
        'to' => 'Guadalajara',
        'duration' => '6h 30m',
        'departure_time_hour' => 25,
        'departure_time_minute' => 0,
    ]);

    $response->assertSessionHasErrors('departure_time_hour');
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
