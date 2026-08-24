<?php

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use App\Models\SeatReservation;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function createHoldableTrip(): array
{
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

    return [$trip, $seat];
}

test('placing a hold succeeds and broadcasts a status update', function () {
    Event::fake([SeatAvailabilityUpdated::class]);
    [$trip, $seat] = createHoldableTrip();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->postJson(route('travel.seats.hold', [$trip, $seat]));

    $response->assertOk()->assertJson(['status' => 'held']);
    $this->assertDatabaseHas('seat_holds', [
        'landing_route_id' => $trip->id,
        'bus_unit_seat_id' => $seat->id,
        'user_id' => $user->id,
    ]);

    $hold = SeatHold::first();
    expect($hold->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(10);

    Event::assertDispatched(SeatAvailabilityUpdated::class, fn ($event) => $event->landingRouteId === $trip->id
        && $event->seats[0]['id'] === $seat->id
        && $event->seats[0]['status'] === 'held'
    );
});

test('a seat already held by another user cannot be held', function () {
    [$trip, $seat] = createHoldableTrip();
    $firstUser = User::factory()->create(['email_verified_at' => now()]);
    $secondUser = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($firstUser)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertOk();

    $this->actingAs($secondUser)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertStatus(409);

    $this->assertDatabaseCount('seat_holds', 1);
    $this->assertDatabaseHas('seat_holds', ['bus_unit_seat_id' => $seat->id, 'user_id' => $firstUser->id]);
});

test('a user can re-hold their own already-held seat without conflict', function () {
    [$trip, $seat] = createHoldableTrip();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertOk();
    $this->actingAs($user)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertOk();

    $this->assertDatabaseCount('seat_holds', 1);
});

test('an expired hold can be taken over by a different user', function () {
    [$trip, $seat] = createHoldableTrip();
    $firstUser = User::factory()->create(['email_verified_at' => now()]);
    $secondUser = User::factory()->create(['email_verified_at' => now()]);

    SeatHold::create([
        'landing_route_id' => $trip->id,
        'bus_unit_seat_id' => $seat->id,
        'user_id' => $firstUser->id,
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($secondUser)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertOk();

    $this->assertDatabaseCount('seat_holds', 1);
    $this->assertDatabaseHas('seat_holds', ['bus_unit_seat_id' => $seat->id, 'user_id' => $secondUser->id]);
});

test('releasing a hold deletes it and frees the seat', function () {
    [$trip, $seat] = createHoldableTrip();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertOk();
    $this->actingAs($user)->deleteJson(route('travel.seats.hold.destroy', [$trip, $seat]))
        ->assertOk()
        ->assertJson(['status' => 'available']);

    $this->assertDatabaseCount('seat_holds', 0);
});

test('a seat with an already-purchased reservation cannot be held', function () {
    [$trip, $seat] = createHoldableTrip();
    $buyer = User::factory()->create(['email_verified_at' => now()]);
    $laterUser = User::factory()->create(['email_verified_at' => now()]);

    SeatReservation::create([
        'landing_route_id' => $trip->id,
        'bus_unit_seat_id' => $seat->id,
        'user_id' => $buyer->id,
    ]);

    $this->actingAs($laterUser)->postJson(route('travel.seats.hold', [$trip, $seat]))->assertStatus(409);
    $this->assertDatabaseCount('seat_holds', 0);
});
