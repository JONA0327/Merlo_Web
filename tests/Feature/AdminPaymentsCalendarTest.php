<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\User;

function pagosAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
}

function pagosTrip(\Illuminate\Support\Carbon $day): array
{
    $bus = BusUnit::create(['name' => 'Bus Pagos '.$day->toDateString()]);
    $seats = collect(['1', '2'])->map(fn ($l, $i) => BusUnitSeat::create([
        'bus_unit_id' => $bus->id, 'label' => $l, 'type' => 'normal', 'pos_x' => $i, 'pos_y' => 0,
    ]));
    $trip = LandingRoute::create([
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 9,
        'bus_unit_id' => $bus->id, 'is_active' => true, 'day' => $day->toDateString(),
    ]);

    return [$trip, $seats];
}

function pagosRow(LandingRoute $trip, BusUnitSeat $seat, array $extra = []): SeatReservation
{
    return SeatReservation::create($extra + [
        'landing_route_id' => $trip->id, 'bus_unit_seat_id' => $seat->id, 'trip_type' => 'one_way',
        'leg' => 'outbound', 'unit_price' => 100, 'total' => 0, 'customer_name' => 'Cliente Prueba',
        'customer_phone' => '4444416578', 'status' => 'sent', 'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]);
}

test('pagos defaults to today and scopes the list to the selected day, not everything ever', function () {
    $admin = pagosAdmin();
    $today = now()->startOfDay();
    $otherDay = $today->copy()->addDays(2);

    [$todayTrip, $todaySeats] = pagosTrip($today);
    [$otherTrip, $otherSeats] = pagosTrip($otherDay);
    $todayRow = pagosRow($todayTrip, $todaySeats[0], ['payment_status' => 'completed']);
    $otherRow = pagosRow($otherTrip, $otherSeats[0]);

    $response = $this->actingAs($admin)->get(route('admin.pagos.index'));

    $response->assertOk();
    $response->assertSee($todayRow->ticket_code);
    $response->assertDontSee($otherRow->ticket_code);
});

test('selecting a day on the strip shows only that day\'s payments', function () {
    $admin = pagosAdmin();
    $dayA = now()->addDays(3)->startOfDay();
    $dayB = now()->addDays(4)->startOfDay();

    [$tripA, $seatsA] = pagosTrip($dayA);
    [$tripB, $seatsB] = pagosTrip($dayB);
    $rowA = pagosRow($tripA, $seatsA[0]);
    $rowB = pagosRow($tripB, $seatsB[0]);

    $response = $this->actingAs($admin)->get(route('admin.pagos.index', ['fecha' => $dayA->toDateString()]));

    $response->assertOk();
    $response->assertSee($rowA->ticket_code);
    $response->assertDontSee($rowB->ticket_code);
});

test('a past day shows the estimated-total card and a stale pending reservation reads as fuera de tiempo', function () {
    $admin = pagosAdmin();
    $pastDay = now()->subDays(5)->startOfDay();

    [$trip, $seats] = pagosTrip($pastDay);
    $paid = pagosRow($trip, $seats[0], ['payment_status' => 'completed']);
    $stale = pagosRow($trip, $seats[1], ['payment_status' => 'pending']);

    $response = $this->actingAs($admin)->get(route('admin.pagos.index', ['fecha' => $pastDay->toDateString()]));

    $response->assertOk();
    $response->assertSee('Total estimado del día');
    $response->assertSee('Fuera de tiempo');
    $response->assertSee('#'.$paid->id);
    $response->assertSee('#'.$stale->id);
});

test('today does not show the estimated-total card', function () {
    $admin = pagosAdmin();

    $response = $this->actingAs($admin)->get(route('admin.pagos.index'));

    $response->assertOk();
    $response->assertDontSee('Total estimado del día');
});

test('searching by reference bypasses the day scope entirely', function () {
    $admin = pagosAdmin();
    [$trip, $seats] = pagosTrip(now()->addDays(10)->startOfDay());
    $row = pagosRow($trip, $seats[0], ['transfer_reference' => 'REF-UNIQUE-123']);

    $response = $this->actingAs($admin)->get(route('admin.pagos.index', ['q' => 'REF-UNIQUE-123']));

    $response->assertOk();
    $response->assertSee('#'.$row->id);
    $response->assertDontSee('Semana anterior');
});
