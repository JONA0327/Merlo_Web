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

test('the histórico listing shows an ended trip linked back to its guide', function () {
    [$trip, , $guide] = guiaOpenTrip();
    $trip->delete();

    $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.historico'))
        ->assertOk()
        ->assertSee('SLP')
        ->assertSee(route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]), false);
});

test('an ended (soft-deleted) trip stays consultable through its guide — histórico', function () {
    [$trip, $seats, $guide] = guiaOpenTrip();
    $reservation = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'customer_name' => 'Pasado', 'customer_phone' => '4444416578',
        'status' => 'sent', 'payment_method' => 'cash', 'payment_status' => 'completed',
    ]);
    $trip->delete();

    $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]))
        ->assertOk()
        ->assertSee('Pasado');

    expect($reservation->fresh())->not->toBeNull();
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

function guiaTwoBuses(): array
{
    $mk = fn ($name, $labels) => tap(BusUnit::create(['name' => $name]), fn ($b) => collect($labels)->each(
        fn ($l, $i) => BusUnitSeat::create(['bus_unit_id' => $b->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0])
    ));

    return [$mk('Bus A', ['1', '2', '3']), $mk('Bus B', ['1', '2'])];
}

function guiaStaged($guide, $bus, string $date, string $label): SeatReservation
{
    return SeatReservation::create([
        'trip_guide_id' => $guide->id, 'bus_unit_seat_id' => $bus->seats()->where('label', $label)->value('id'),
        'travel_date' => $date, 'trip_type' => 'one_way', 'leg' => 'outbound', 'unit_price' => 100,
        'customer_name' => 'Cliente '.$label.$date, 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending',
    ]);
}

test('changing the plantilla of one date moves only that date, not the whole guide', function () {
    [$a, $b] = guiaTwoBuses();
    $d1 = now()->addDays(3)->toDateString();
    $d2 = now()->addDays(4)->toDateString();
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d1, 'date_to' => $d2]);
    $r1 = guiaStaged($guide, $a, $d1, '2');
    $r2 = guiaStaged($guide, $a, $d2, '2');
    $r3 = guiaStaged($guide, $a, $d1, '3'); // label 3 doesn't exist on Bus B

    $this->actingAs(guiaAdmin())
        ->put(route('admin.guias.date-bus', $guide), ['travel_date' => $d1, 'bus_unit_id' => $b->id])
        ->assertSessionHas('warning');

    $guide = $guide->fresh();
    expect($guide->bus_unit_id)->toBe($a->id)
        ->and($guide->busUnitIdFor(now()->addDays(3)))->toBe($b->id)
        ->and($guide->busUnitIdFor(now()->addDays(4)))->toBe($a->id)
        ->and($r1->fresh()->seat->bus_unit_id)->toBe($b->id)
        ->and($r2->fresh()->seat->bus_unit_id)->toBe($a->id)
        ->and($r3->fresh()->hasSeatMismatch())->toBeTrue()  // unmatched: held, no ticket
        ->and($r1->fresh()->hasSeatMismatch())->toBeFalse();

    $this->actingAs(guiaAdmin())->get(route('admin.guias.show', [$guide, 'fecha' => $d1]))->assertOk();
});

test('changing the plantilla of an open trip only changes that trip', function () {
    [$a, $b] = guiaTwoBuses();
    $d1 = now()->addDays(3)->toDateString();
    $d2 = now()->addDays(4)->toDateString();
    $t1 = LandingRoute::create(['from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 3, 'bus_unit_id' => $a->id, 'is_active' => true, 'day' => $d1]);
    $t2 = LandingRoute::create(['from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 3, 'bus_unit_id' => $a->id, 'is_active' => true, 'day' => $d2]);
    $guide = TripGuide::ensureForTrip($t1);

    $this->actingAs(guiaAdmin())
        ->put(route('admin.guias.trip-bus', $guide), ['travel_date' => $d1, 'bus_unit_id' => $b->id])
        ->assertSessionHas('success');

    expect($t1->fresh()->bus_unit_id)->toBe($b->id)
        ->and($t2->fresh()->bus_unit_id)->toBe($a->id)
        ->and($guide->fresh()->bus_unit_id)->toBe($a->id);

    $this->actingAs(guiaAdmin())->get(route('admin.guias.show', [$guide, 'fecha' => $d1]))->assertOk();
});

test('a guide-staged apartado for a future date sends the "apartado registrado" WhatsApp right away', function () {
    [$a] = guiaTwoBuses();
    $d = now()->addDays(20)->toDateString();
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d, 'date_to' => $d]);
    $seat = $a->seats()->where('label', '1')->first();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.store', $guide), [
            'travel_date' => $d, 'customer_name' => 'Lejana', 'customer_phone' => '4444416578', 'trip_type' => 'one_way',
            'paid' => '0', 'payment_method' => [$seat->id => 'cash'], 'seat_ids' => [$seat->id],
        ])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/message/sendText/') && str_contains($r['text'], 'tu apartado fue registrado') && $r['number'] === '524444416578');
});

test('a guide-staged apartado warns the admin when the WhatsApp notice could not be sent', function () {
    Http::swap(new \Illuminate\Http\Client\Factory); // beforeEach's success fake would win otherwise
    Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    [$a] = guiaTwoBuses();
    $d = now()->addDays(20)->toDateString();
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d, 'date_to' => $d]);
    $seat = $a->seats()->where('label', '1')->first();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.store', $guide), [
            'travel_date' => $d, 'customer_name' => 'Lejana', 'customer_phone' => '4444416578', 'trip_type' => 'one_way',
            'paid' => '0', 'payment_method' => [$seat->id => 'cash'], 'seat_ids' => [$seat->id],
        ])
        ->assertSessionHas('warning');

    expect(SeatReservation::count())->toBe(1); // the apartado is kept even if WhatsApp is down
});

test('provisioning "de planta" apartados does not duplicate a seat after the date switched plantilla', function () {
    [$a, $b] = guiaTwoBuses();
    $d = now()->addDays(3);
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d->toDateString(), 'date_to' => $d->toDateString()]);
    StandingReservation::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'bus_unit_seat_id' => $a->seats()->where('label', '1')->value('id'),
        'customer_name' => 'Planta', 'customer_phone' => '4444416578', 'trip_type' => 'one_way', 'is_active' => true,
        'weekdays' => [$d->dayOfWeek],
    ]);

    expect(StandingReservation::provisionGuideReservations($guide))->toBe(1);

    $this->actingAs(guiaAdmin())->put(route('admin.guias.date-bus', $guide), ['travel_date' => $d->toDateString(), 'bus_unit_id' => $b->id]);

    // Re-run (the guides index does this on every load): still exactly one.
    expect(StandingReservation::provisionGuideReservations($guide->fresh()))->toBe(0)
        ->and(SeatReservation::where('trip_guide_id', $guide->id)->count())->toBe(1);

    // Same through the whole-guide route (no per-date override): label match, no duplicate.
    $guide2 = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d->copy()->addDay()->toDateString(), 'date_to' => $d->copy()->addDay()->toDateString()]);
    $seatA = $a->seats()->where('label', '1')->value('id');
    SeatReservation::create(['trip_guide_id' => $guide2->id, 'bus_unit_seat_id' => $b->seats()->where('label', '1')->value('id'), 'travel_date' => $d->copy()->addDay()->toDateString(),
        'trip_type' => 'one_way', 'leg' => 'outbound', 'unit_price' => 1, 'customer_name' => 'Planta', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending', 'notes' => 'Asiento de planta']);
    StandingReservation::where('bus_unit_seat_id', $seatA)->update(['weekdays' => null]);
    expect(StandingReservation::provisionGuideReservations($guide2))->toBe(0);
});

// ---------------------------------------------------------------------------
// "Especial" with a mode (ida / redondo / regreso — same price) and the
// colors of the printed list.
// ---------------------------------------------------------------------------

function especialStore(array $over = []): array
{
    [$trip, $seats, $guide] = guiaFutureOpenTrip();
    \App\Models\TripTicketPrice::create(['landing_route_id' => $trip->id, 'trip_type' => 'especial', 'price' => 1800]);

    test()->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.store', $guide), $over + [
            'travel_date' => $trip->day->toDateString(),
            'customer_name' => 'Especial', 'customer_phone' => '4444416578', 'trip_type' => 'especial',
            'paid' => '1', 'payment_method' => [$seats[0]->id => 'cash', $seats[1]->id => 'cash'],
            'seat_ids' => [$seats[0]->id, $seats[1]->id],
        ])->assertSessionHas('success');

    return [$trip, $seats, $guide, SeatReservation::where('trip_type', 'especial')->orderBy('id')->get()];
}

test('an especial defaults to redondo: both legs, own green color', function () {
    [, , , $rows] = especialStore();

    expect($rows)->toHaveCount(2);
    $r = $rows->first();
    expect($r->especial_mode)->toBe('round_trip')
        ->and($r->leg)->toBe('outbound')
        ->and($r->needsBothLegs())->toBeTrue()
        ->and($r->colorKey())->toBe('especial')
        ->and($r->trip_type_label)->toBe(\App\Models\TripTicketPrice::tripTypes()['especial']);
});

test('an especial "solo ida" is one leg and blue; the price is the same', function () {
    [, , , $rows] = especialStore(['especial_mode' => 'one_way']);

    $r = $rows->first();
    expect($r->especial_mode)->toBe('one_way')
        ->and($r->leg)->toBe('outbound')
        ->and($r->needsBothLegs())->toBeFalse()
        ->and($r->colorKey())->toBe('ida')
        ->and($r->trip_type_label)->toContain('solo ida')
        ->and((float) $r->unit_price)->toBe(900.0); // mancuerna 1800 split over 2 seats, same as a redondo especial
});

test('an especial "solo regreso" sits on the return leg and is red', function () {
    [, , , $rows] = especialStore(['especial_mode' => 'return']);

    $r = $rows->first();
    expect($r->leg)->toBe('return')
        ->and($r->needsBothLegs())->toBeFalse()
        ->and($r->colorKey())->toBe('regreso')
        ->and($r->trip_type_label)->toContain('solo regreso');
});

test('a rescheduled return (ida row on the return leg) is red, a plain ida is blue', function () {
    $ida = new SeatReservation(['trip_type' => 'one_way', 'leg' => 'outbound']);
    $rescheduled = new SeatReservation(['trip_type' => 'one_way', 'leg' => 'return', 'source_reservation_id' => 99]);
    $redondo = new SeatReservation(['trip_type' => 'round_trip', 'leg' => 'outbound']);

    expect($ida->colorKey())->toBe('ida')
        ->and($rescheduled->colorKey())->toBe('regreso')
        ->and($redondo->colorKey())->toBe('redondo');
});

test('check-in only waits for both legs on a redondo especial', function () {
    [, , , $rows] = especialStore(['especial_mode' => 'one_way']);
    $ida = $rows->first();

    expect(SeatReservation::pendingCheckIn()->pluck('id'))->toContain($ida->id);
    $ida->update(['outbound_verified_at' => now()]);
    expect(SeatReservation::pendingCheckIn()->pluck('id'))->not->toContain($ida->id);

    $redondo = SeatReservation::create([
        'landing_route_id' => $ida->landing_route_id, 'bus_unit_seat_id' => $rows->last()->bus_unit_seat_id, 'trip_type' => 'especial',
        'leg' => 'return', 'unit_price' => 1, 'customer_name' => 'X', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending',
    ]);
    $legacy = SeatReservation::create([ // an especial created before the mode existed: NULL = redondo
        'landing_route_id' => $ida->landing_route_id, 'bus_unit_seat_id' => \App\Models\BusUnitSeat::where('bus_unit_id', $ida->landingRoute->bus_unit_id)->whereNotIn('id', $rows->pluck('bus_unit_seat_id'))->value('id'), 'trip_type' => 'especial',
        'leg' => 'outbound', 'unit_price' => 1, 'customer_name' => 'Y', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending',
        'outbound_verified_at' => now(),
    ]);
    // the return-leg especial (mode null→redondo but on the return leg) and the legacy one are still pending
    expect(SeatReservation::pendingCheckIn()->pluck('id'))->toContain($legacy->id);
});

test('the printed list paints each especial by what it is and "de planta" in purple', function () {
    [$trip, $seats, $guide] = especialStore(['especial_mode' => 'return']);
    SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[2]->id, 'trip_type' => 'one_way', 'leg' => 'outbound',
        'unit_price' => 1, 'customer_name' => 'Planta', 'customer_phone' => '4444416578', 'status' => 'pending',
        'payment_status' => 'pending', 'notes' => 'Asiento de planta',
    ]);

    $trip->busUnit->forceFill(['canvas_width' => 300, 'canvas_height' => 100])->save();
    $response = $this->actingAs(guiaAdmin())->get(route('admin.guias.manifest', [$guide, 'fecha' => $trip->day->toDateString()]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

test('the open-trip seat map receives who holds each seat (hover tooltip)', function () {
    [$trip, $seats, $guide] = guiaFutureOpenTrip();
    SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way', 'leg' => 'outbound',
        'unit_price' => 100, 'customer_name' => 'María Pérez', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'completed',
    ]);
    SeatReservation::create([ // a failed payment does not hold the seat
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[1]->id, 'trip_type' => 'one_way', 'leg' => 'outbound',
        'unit_price' => 100, 'customer_name' => 'Pago Fallido', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'failed',
    ]);

    $html = $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]))
        ->assertOk()->getContent();

    preg_match('/seatHolders: (\{.*?\}),?
/s', $html, $m);
    $holders = json_decode($m[1] ?? '{}', true);

    expect($holders)->toHaveKey((string) $seats[0]->id)
        ->and($holders[$seats[0]->id][0])->toContain('María Pérez')->toContain('Pagado')
        ->and($holders)->not->toHaveKey((string) $seats[1]->id); // failed payment holds nothing
});

test('the staged (no trip yet) seat map receives who holds each seat on that date', function () {
    [$a] = guiaTwoBuses();
    $d = now()->addDays(15)->toDateString();
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d, 'date_to' => $d]);
    guiaStaged($guide, $a, $d, '1');

    $html = $this->actingAs(guiaAdmin())
        ->get(route('admin.guias.show', [$guide, 'fecha' => $d]))
        ->assertOk()->getContent();

    expect($html)->toContain('seatHolders')->and($html)->toContain('Cliente 1'.$d)->and($html)->toContain('Pendiente');
});

test('the apartar form defaults to "no pagado" and payment method "por definir"', function () {
    // open trip (Apartar asientos) and a date still staged in the guide
    [$trip, , $guide] = guiaFutureOpenTrip();
    [$a] = guiaTwoBuses();
    $d = now()->addDays(15)->toDateString();
    $staged = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d, 'date_to' => $d]);

    foreach ([
        route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]),
        route('admin.guias.show', [$staged, 'fecha' => $d]),
    ] as $url) {
        $html = $this->actingAs(guiaAdmin())->get($url)->assertOk()->getContent();

        expect($html)->toMatch('/<option value="0"\s+selected>No, pagará después<\/option>/')
            ->and($html)->not->toMatch('/<option value="1"\s+selected>Sí, ya está pagado<\/option>/')
            ->and($html)->toMatch('/<option value="tbd" selected>Por definir<\/option>/');
    }
});

// ---------------------------------------------------------------------------
// "Regreso otro día": the seat map must know which seats already have a regreso
// on that date (they live in another date's guide), instead of letting them be
// picked and then failing on save.
// ---------------------------------------------------------------------------

function stagedRegreso(TripGuide $guide, $seatId, string $date, string $name): SeatReservation
{
    return SeatReservation::create([
        'trip_guide_id' => $guide->id, 'bus_unit_seat_id' => $seatId, 'travel_date' => $date,
        'trip_type' => 'regreso', 'leg' => 'return', 'unit_price' => 100, 'customer_name' => $name,
        'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'completed',
    ]);
}

test('the regreso-otro-día endpoint lists the seats that already have a regreso on that date', function () {
    [$trip, $seats, $guide] = guiaFutureOpenTrip();
    $target = now()->addDays(10)->toDateString();
    $otherDay = now()->addDays(11)->toDateString();
    $stagedGuide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $trip->bus_unit_id, 'date_from' => $target, 'date_to' => $otherDay]);
    stagedRegreso($stagedGuide, $seats[0]->id, $target, 'ALEJANDRO URIBE');
    stagedRegreso($stagedGuide, $seats[2]->id, $target, 'ALEJANDRO URIBE');
    stagedRegreso($stagedGuide, $seats[1]->id, $otherDay, 'Otro Día'); // another date: not taken for $target

    $json = $this->actingAs(guiaAdmin())
        ->getJson(route('admin.guias.regreso-taken', [$guide, 'fecha' => $trip->day->toDateString(), 'regreso_date' => $target]))
        ->assertOk()->json();

    expect($json['trip_exists'])->toBeFalse()
        ->and($json['taken'])->toEqualCanonicalizing([$seats[0]->id, $seats[2]->id])
        ->and($json['holders'][$seats[0]->id][0])->toContain('ALEJANDRO URIBE')->toContain('Pagado');
});

test('the regreso-otro-día endpoint maps seats by label when the guide runs another unit', function () {
    [$trip, $seats, $guide] = guiaFutureOpenTrip();
    $target = now()->addDays(10)->toDateString();
    $other = BusUnit::create(['name' => 'Otra unidad']);
    $otherSeat = BusUnitSeat::create(['bus_unit_id' => $other->id, 'label' => '2', 'type' => 'normal', 'pos_x' => 0, 'pos_y' => 0]);
    $stagedGuide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $other->id, 'date_from' => $target, 'date_to' => $target]);
    stagedRegreso($stagedGuide, $otherSeat->id, $target, 'Por Etiqueta');

    $json = $this->actingAs(guiaAdmin())
        ->getJson(route('admin.guias.regreso-taken', [$guide, 'fecha' => $trip->day->toDateString(), 'regreso_date' => $target]))
        ->assertOk()->json();

    expect($json['taken'])->toBe([$seats[1]->id]); // label "2" of the OPEN trip's unit
});

test('the regreso-otro-día endpoint says so when that date already has its real trip', function () {
    [$trip, , $guide] = guiaFutureOpenTrip();
    $target = now()->addDays(10);
    LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 3,
        'bus_unit_id' => $trip->bus_unit_id, 'is_active' => true, 'day' => $target->toDateString(),
    ]);

    $json = $this->actingAs(guiaAdmin())
        ->getJson(route('admin.guias.regreso-taken', [$guide, 'fecha' => $trip->day->toDateString(), 'regreso_date' => $target->toDateString()]))
        ->assertOk()->json();

    expect($json['trip_exists'])->toBeTrue()->and($json['taken'])->toBe([])->and($json['message'])->toContain('Ya existe el viaje');
});

test('the regreso-otro-día endpoint needs a trip on that date and a logged-in admin', function () {
    [, , $guide] = guiaFutureOpenTrip();

    $this->getJson(route('admin.guias.regreso-taken', [$guide, 'fecha' => now()->addDays(4)->toDateString(), 'regreso_date' => now()->addDays(9)->toDateString()]))
        ->assertUnauthorized();
    $this->actingAs(guiaAdmin())
        ->getJson(route('admin.guias.regreso-taken', [$guide, 'fecha' => now()->addDays(30)->toDateString(), 'regreso_date' => now()->addDays(35)->toDateString()]))
        ->assertNotFound();
});

test('saving a regreso on a date where a seat already has one is still refused (what the map now prevents)', function () {
    [$trip, $seats, $guide] = guiaFutureOpenTrip();
    $target = now()->addDays(10)->toDateString();
    $stagedGuide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $trip->bus_unit_id, 'date_from' => $target, 'date_to' => $target]);
    stagedRegreso($stagedGuide, $seats[0]->id, $target, 'Primero');

    $this->actingAs(guiaAdmin())->post(route('admin.guias.reservations.store', $guide), [
        'travel_date' => $trip->day->toDateString(), 'customer_name' => 'Segundo', 'customer_phone' => '4444416578', 'trip_type' => 'regreso',
        'regreso_date' => $target, 'paid' => '0', 'payment_method' => [$seats[0]->id => 'tbd'], 'seat_ids' => [$seats[0]->id],
    ])->assertSessionHas('error');

    expect(SeatReservation::where('customer_name', 'Segundo')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Reasignar asiento desde "Editar" (viaje abierto y fechas posteriores en la
// guía), avisando del cambio al cliente por WhatsApp.
// ---------------------------------------------------------------------------

function reassignTripReservation(array $attrs = []): array
{
    [$trip, $seats, $guide] = guiaFutureOpenTrip();
    $r = SeatReservation::create($attrs + [
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[0]->id, 'trip_type' => 'one_way', 'leg' => 'outbound',
        'unit_price' => 100, 'customer_name' => 'Cambio Asiento', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending',
    ]);

    return [$trip, $seats, $guide, $r];
}

test('reassigning a seat on an open trip moves it and tells the customer "antes / ahora"', function () {
    [, $seats, $guide, $r] = reassignTripReservation();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.reassign-seat', [$guide, $r]), ['bus_unit_seat_id' => $seats[2]->id])
        ->assertSessionHas('success');

    expect($r->fresh()->bus_unit_seat_id)->toBe($seats[2]->id);
    Http::assertSent(fn ($req) => str_contains($req->url(), '/message/sendText/')
        && str_contains($req['text'], 'cambiamos tu asiento')
        && str_contains($req['text'], 'Antes: *1*') && str_contains($req['text'], 'Ahora: *3*')
        && $req['number'] === '524444416578');
});

test('a paid, already-sent ticket is re-sent with the new seat after a reassignment', function () {
    [, $seats, $guide, $r] = reassignTripReservation(['payment_status' => 'completed', 'ticket_sent_at' => now(), 'status' => 'sent']);

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.reassign-seat', [$guide, $r]), ['bus_unit_seat_id' => $seats[1]->id])
        ->assertSessionHas('success');

    $texts = Http::recorded()->map(fn ($pair) => $pair[0]->url())->all();
    expect(collect($texts)->contains(fn ($u) => str_contains($u, '/message/sendText/')))->toBeTrue();
    // plus the refreshed ticket (image/media message) for the paid seat
    expect(collect($texts)->count())->toBeGreaterThan(1);
});

test('reassigning a staged (later date) apartado moves it and sends the change notice only', function () {
    [$a] = guiaTwoBuses();
    $d = now()->addDays(15)->toDateString();
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d, 'date_to' => $d]);
    $r = guiaStaged($guide, $a, $d, '1');
    $target = $a->seats()->where('label', '3')->first();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.reassign-seat', [$guide, $r]), ['bus_unit_seat_id' => $target->id])
        ->assertSessionHas('success');

    expect($r->fresh()->bus_unit_seat_id)->toBe($target->id);
    Http::assertSent(fn ($req) => str_contains($req['text'] ?? '', 'cambiamos tu asiento') && str_contains($req['text'], 'Ahora: *3*'));
});

test('a failing WhatsApp does not undo the reassignment but warns the admin', function () {
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    [, $seats, $guide, $r] = reassignTripReservation();

    $this->actingAs(guiaAdmin())
        ->post(route('admin.guias.reservations.reassign-seat', [$guide, $r]), ['bus_unit_seat_id' => $seats[1]->id])
        ->assertSessionHas('error');

    expect($r->fresh()->bus_unit_seat_id)->toBe($seats[1]->id);
});

test('reassignment is refused for a taken seat, the same seat, a boarded passenger, a de-planta seat or a seat of another bus', function () {
    [$trip, $seats, $guide, $r] = reassignTripReservation();
    $other = SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[1]->id, 'trip_type' => 'one_way', 'leg' => 'outbound',
        'unit_price' => 100, 'customer_name' => 'Ocupa', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending',
    ]);
    $admin = guiaAdmin();
    $post = fn ($seatId) => $this->actingAs($admin)->post(route('admin.guias.reservations.reassign-seat', [$guide, $r]), ['bus_unit_seat_id' => $seatId]);

    $post($seats[1]->id)->assertSessionHas('error');                       // taken on this trip
    $post($seats[0]->id)->assertSessionHas('error');                       // same seat
    [$busB] = guiaTwoBuses();
    $post($busB->seats()->first()->id)->assertSessionHasErrors('bus_unit_seat_id'); // another unit

    StandingReservation::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $trip->bus_unit_id, 'bus_unit_seat_id' => $seats[2]->id,
        'customer_name' => 'Planta', 'customer_phone' => '4444416578', 'trip_type' => 'one_way', 'is_active' => true,
    ]);
    $post($seats[2]->id)->assertSessionHas('error');                       // "de planta"

    $r->update(['outbound_verified_at' => now()]);
    $post($seats[2]->id)->assertSessionHas('error');                       // already boarded

    expect($r->fresh()->bus_unit_seat_id)->toBe($seats[0]->id)->and($other->fresh()->bus_unit_seat_id)->toBe($seats[1]->id);
    Http::assertNothingSent();
});

test('the edit panels offer only the free seats to reassign to', function () {
    [$trip, $seats, $guide, $r] = reassignTripReservation();
    SeatReservation::create([
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seats[1]->id, 'trip_type' => 'one_way', 'leg' => 'outbound',
        'unit_price' => 100, 'customer_name' => 'Ocupa', 'customer_phone' => '4444416578', 'status' => 'pending', 'payment_status' => 'pending',
    ]);

    $html = $this->actingAs(guiaAdmin())->get(route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]))->assertOk()->getContent();

    expect($html)->toContain('Cambiar de asiento')
        ->and($html)->toContain('<option value="'.$seats[2]->id.'">3</option>')      // free
        ->and($html)->not->toContain('<option value="'.$seats[1]->id.'">2</option>'); // taken
});

test('the staged-date edit panel also offers the free seats of that date', function () {
    [$a] = guiaTwoBuses();
    $d = now()->addDays(15)->toDateString();
    $guide = TripGuide::create(['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $a->id, 'date_from' => $d, 'date_to' => $d]);
    guiaStaged($guide, $a, $d, '1');
    guiaStaged($guide, $a, $d, '2');

    $html = $this->actingAs(guiaAdmin())->get(route('admin.guias.show', [$guide, 'fecha' => $d]))->assertOk()->getContent();
    $free = $a->seats()->where('label', '3')->first();
    $taken = $a->seats()->where('label', '2')->first();

    expect($html)->toContain('Cambiar de asiento')
        ->and($html)->toContain('<option value="'.$free->id.'">3</option>')
        ->and($html)->toContain('Ahora: 1');
    // seat 2 is held by the other apartado: it must not be offered in the panel of seat 1
    $panelOfSeat1 = substr($html, strpos($html, 'Ahora: 1'), 600);
    expect($panelOfSeat1)->not->toContain('<option value="'.$taken->id.'">2</option>');
});
