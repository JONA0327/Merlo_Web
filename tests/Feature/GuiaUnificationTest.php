<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\StandingReservation;
use App\Models\TripGuide;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function guiaAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
}

function guiaOpenTrip(): array
{
    $bus = BusUnit::create(['name' => 'Bus Guia']);
    $seats = collect(['1', '2', '3'])->map(fn ($l, $i) => BusUnitSeat::create([
        'bus_unit_id' => $bus->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0,
    ]));
    $trip = LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $bus->id, 'is_active' => true, 'day' => now()->toDateString(),
    ]);
    $guide = TripGuide::ensureForTrip($trip);

    return [$trip, $seats, $guide];
}

function guiaFutureOpenTrip(): array
{
    $bus = BusUnit::create(['name' => 'Bus Guia Futuro']);
    $seats = collect(['1', '2', '3'])->map(fn ($l, $i) => BusUnitSeat::create([
        'bus_unit_id' => $bus->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0,
    ]));
    $trip = LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $bus->id, 'is_active' => true, 'day' => now()->addDays(4)->toDateString(),
    ]);
    $guide = TripGuide::ensureForTrip($trip);

    return [$trip, $seats, $guide];
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    Setting::current()->update([
        'evolution_api_url' => 'https://example.test',
        'evolution_api_key' => 'test-key',
        'evolution_instance' => 'test-instance',
    ]);
});

test('show renders the real trip through the guide without redirecting', function () {
    [$trip, , $guide] = guiaOpenTrip();

    $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]))
        ->assertOk()
        ->assertSee('SLP');
});

test('storing a reservation on an open trip via the guide creates it against the real trip and sends only the reservation notice', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.store', $guide), [
            'travel_date' => $trip->day->toDateString(),
            'customer_name' => 'Ana', 'customer_phone' => '4444416578', 'trip_type' => 'one_way',
            'paid' => '0', 'payment_method' => [$seats[0]->id => 'cash'], 'seat_ids' => [$seats[0]->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reservation = SeatReservation::where('customer_name', 'Ana')->sole();
    expect($reservation->landing_route_id)->toBe($trip->id);
    expect($reservation->trip_guide_id)->toBeNull();
});

test('storing a reservation on a far-future open trip only sends the reservation notice, deferring the ticket to the day-before job', function () {
    [$trip, $seats, $guide] = guiaFutureOpenTrip();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.store', $guide), [
            'travel_date' => $trip->day->toDateString(),
            'customer_name' => 'Luz', 'customer_phone' => '4444416578', 'trip_type' => 'one_way',
            'paid' => '1', 'payment_method' => [$seats[0]->id => 'cash'], 'seat_ids' => [$seats[0]->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reservation = SeatReservation::where('customer_name', 'Luz')->sole();
    expect($reservation->landing_route_id)->toBe($trip->id);
    expect($reservation->reminder_sent_at)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/message/sendMedia/'));

    return [$trip, $seats, $guide, $reservation];
});

test('sendTicket sends the real ticket for a paid reservation on an open trip', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();
    $reservation = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'pending', 'payment_method' => 'cash', 'payment_status' => 'completed',
    ]);

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.send', [$guide, $reservation]))
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('updateCategory updates a reservation on the open trip', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();
    $reservation = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'pending', 'payment_method' => 'cash', 'payment_status' => 'pending',
    ]);

    $this->actingAs(guiaAdmin())
        ->put(route('admin.guias.reservations.update-category', [$guide, $reservation]), [
            'trip_type' => 'one_way', 'payment_method' => 'cash', 'payment_status' => 'completed',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($reservation->fresh()->payment_status)->toBe('completed');
});

test('removeSeat drops one seat from a multi-seat reservation on the open trip', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();
    $root = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'pending', 'payment_method' => 'cash', 'payment_status' => 'pending',
    ]);
    $member = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[1]->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'pending', 'payment_method' => 'cash', 'payment_status' => 'pending',
        'notes' => 'group:'.$root->id,
    ]);

    $this->actingAs(guiaAdmin())
        ->delete(route('admin.guias.reservations.remove-seat', [$guide, $member]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SeatReservation::find($member->id))->toBeNull();
    expect(SeatReservation::find($root->id))->not->toBeNull();
});

test('destroy cancels a reservation on the open trip', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();
    $reservation = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Ana', 'customer_phone' => '4444416578',
        'status' => 'pending', 'payment_method' => 'cash', 'payment_status' => 'pending',
    ]);

    $this->actingAs(guiaAdmin())
        ->delete(route('admin.guias.reservations.destroy', [$guide, $reservation]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SeatReservation::find($reservation->id))->toBeNull();
});

test('releaseStanding marks a de-planta seat released for the open trip', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();
    $standing = StandingReservation::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $trip->bus_unit_id, 'bus_unit_seat_id' => $seats[0]->id,
        'customer_name' => 'Planta', 'customer_phone' => '4444416578', 'trip_type' => 'one_way', 'is_active' => true,
    ]);

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.release-standing', [$guide, $standing, 'fecha' => $trip->day->toDateString()]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($standing->skips()->where('landing_route_id', $trip->id)->exists())->toBeTrue();
});

test('manifest and availability pages are reachable through the guide', function () {
    [$trip, , $guide] = guiaOpenTrip();

    $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.manifest', [$guide, 'fecha' => $trip->day->toDateString()]))
        ->assertOk();

    $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.availability', [$guide, 'fecha' => $trip->day->toDateString()]))
        ->assertOk()
        ->assertSee('SLP');
});

test('updateAvailability updates a seat allowed_trip_type for the open trip', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();

    $this->actingAs(guiaAdmin())
        ->put(route('admin.guias.availability.update', [$guide, 'fecha' => $trip->day->toDateString()]), [
            'changes' => [
                ['id' => $seats[0]->id, 'allowed_trip_type' => 'one_way'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($seats[0]->fresh()->allowed_trip_type)->toBe('one_way');
});
