<?php

use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\TripGuide;
use App\Models\User;
use Illuminate\Support\Carbon;

// "Nueva guía" (manual guides) vs the automatic guide of a trip: they never merge, a
// manual guide is blue in the weekly strip, it can be recurring or a range ("Hasta"
// optional), and trips to the same place keep their own tab/color.

function srcAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
}

function srcBus(string $name): BusUnit
{
    $bus = BusUnit::create(['name' => $name]);
    BusUnitSeat::create(['bus_unit_id' => $bus->id, 'label' => '1', 'type' => 'normal', 'pos_x' => 0, 'pos_y' => 0]);

    return $bus;
}

function srcTrip(BusUnit $bus, array $over = []): LandingRoute
{
    return LandingRoute::create($over + [
        'from' => 'SLP', 'to' => 'CDMX', 'duration' => '1h', 'price' => '$1', 'available_seats' => 1,
        'bus_unit_id' => $bus->id, 'is_active' => true, 'day' => now()->addDays(2)->toDateString(),
    ]);
}

/** The strip tabs of the day $date, from the Guías index. */
function srcTabs($test, Carbon $date): \Illuminate\Support\Collection
{
    $days = $test->actingAs(srcAdmin())->get(route('admin.guias.index', ['inicio' => $date->toDateString()]))->assertOk()->viewData('days');

    return $days->first(fn ($d) => $d['date']->isSameDay($date))['tabs'];
}

test('"Nueva guía": with only "Desde" the guide is that same day, and it is a manual guide', function () {
    $bus = srcBus('A');
    $d = now()->addDays(5)->toDateString();

    $this->actingAs(srcAdmin())->post(route('admin.guias.store'), [
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'date_from' => $d,
    ])->assertSessionHasNoErrors();

    $g = TripGuide::sole();
    expect($g->date_from->toDateString())->toBe($d)->and($g->date_to->toDateString())->toBe($d)
        ->and($g->isManual())->toBeTrue()->and($g->isRecurring())->toBeFalse();
});

test('"Nueva guía": a range keeps its end date, and "Hasta" before "Desde" is refused', function () {
    $bus = srcBus('A');
    $from = now()->addDays(5);

    $this->actingAs(srcAdmin())->post(route('admin.guias.store'), [
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id,
        'date_from' => $from->toDateString(), 'date_to' => $from->copy()->addDays(3)->toDateString(),
    ])->assertSessionHasNoErrors();
    expect(TripGuide::sole()->date_to->toDateString())->toBe($from->copy()->addDays(3)->toDateString());

    $this->actingAs(srcAdmin())->post(route('admin.guias.store'), [
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id,
        'date_from' => $from->toDateString(), 'date_to' => $from->copy()->subDay()->toDateString(),
    ])->assertSessionHasErrors('date_to');
});

test('"Nueva guía" recurrente: needs weekdays; without dates it starts today and keeps rolling', function () {
    $bus = srcBus('A');
    $admin = srcAdmin();
    $base = ['from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'schedule_mode' => 'weekday'];

    $this->actingAs($admin)->post(route('admin.guias.store'), $base)->assertSessionHasErrors('recurring_weekdays');
    expect(TripGuide::count())->toBe(0);

    $this->actingAs($admin)->post(route('admin.guias.store'), $base + ['recurring_weekdays' => ['3', '1', '1']])->assertSessionHasNoErrors();

    $g = TripGuide::sole();
    expect($g->recurring_weekdays)->toBe([1, 3])
        ->and($g->weekdaysLabel())->toBe('Lun, Mié')
        ->and($g->rolling)->toBeTrue()
        ->and($g->date_from->isToday())->toBeTrue()
        ->and($g->date_to->toDateString())->toBe(now()->addWeeks(4)->toDateString());
});

test('a recurring guide appears in the weekly strip only on its weekdays, as a blue manual guide', function () {
    $bus = srcBus('A');
    $guide = TripGuide::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'source' => 'manual',
        'recurring_weekdays' => [1], 'rolling' => true,
        'date_from' => now()->toDateString(), 'date_to' => now()->addWeeks(4)->toDateString(),
    ]);
    $monday = now()->next(Carbon::MONDAY)->startOfDay();

    $onMonday = srcTabs($this, $monday);
    expect($onMonday)->toHaveCount(1)
        ->and($onMonday->first()['guide']->id)->toBe($guide->id)
        ->and($onMonday->first()['manual'])->toBeTrue()
        ->and($onMonday->first()['color'])->toBe('manual');

    expect(srcTabs($this, $monday->copy()->addDay()))->toHaveCount(0); // Tuesday: nothing
    expect($guide->runsOn($monday))->toBeTrue()->and($guide->runsOn($monday->copy()->addDay()))->toBeFalse();
});

test('a recurring guide only accepts apartados on its own weekdays', function () {
    $bus = srcBus('A');
    $guide = TripGuide::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'source' => 'manual', 'recurring_weekdays' => [1],
        'date_from' => now()->toDateString(), 'date_to' => now()->addWeeks(4)->toDateString(),
    ]);
    $tuesday = now()->next(Carbon::TUESDAY)->toDateString();
    $seat = $bus->seats()->first();

    $this->actingAs(srcAdmin())->post(route('admin.guias.reservations.store', $guide), [
        'travel_date' => $tuesday, 'customer_name' => 'X', 'customer_phone' => '4444416578', 'trip_type' => 'one_way',
        'paid' => '0', 'payment_method' => [$seat->id => 'tbd'], 'seat_ids' => [$seat->id],
    ])->assertSessionHasErrors('travel_date');
});

test('a trip never absorbs a manual guide of the same route and unit', function () {
    $bus = srcBus('A');
    $manual = TripGuide::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'source' => 'manual',
        'date_from' => now()->toDateString(), 'date_to' => now()->addWeeks(2)->toDateString(),
    ]);

    // a dated trip on the same route/unit
    $dated = TripGuide::ensureForTrip(srcTrip($bus));
    // a recurring trip on the same route/unit
    $recurring = TripGuide::ensureForTrip(srcTrip($bus, ['day' => null, 'recurring_weekdays' => [1, 4]]));

    expect($dated->id)->not->toBe($manual->id)->and($recurring->id)->not->toBe($manual->id)
        ->and($dated->isManual())->toBeFalse()->and($recurring->isManual())->toBeFalse()
        ->and($manual->fresh()->date_to->toDateString())->toBe(now()->addWeeks(2)->toDateString()); // untouched
});

test('the strip shows the manual guide AND the trip to the same place, each its own tab with its own color', function () {
    $bus = srcBus('A');
    $day = now()->addDays(2);
    TripGuide::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'source' => 'manual',
        'date_from' => $day->toDateString(), 'date_to' => $day->toDateString(),
    ]);
    $trip = srcTrip($bus, ['day' => $day->toDateString(), 'color' => 'green']);
    TripGuide::ensureForTrip($trip);

    $tabs = srcTabs($this, $day->copy()->startOfDay());
    expect($tabs)->toHaveCount(2)
        ->and($tabs->pluck('color')->all())->toEqualCanonicalizing(['manual', 'green']);
});

test('two trips to the same place with different units each keep their guide, tab and color', function () {
    $busA = srcBus('A');
    $busB = srcBus('B');
    $day = now()->addDays(2);

    $a = srcTrip($busA, ['day' => $day->toDateString(), 'color' => 'orange']);
    $b = srcTrip($busB, ['day' => $day->toDateString(), 'color' => 'purple']);
    $guideA = TripGuide::ensureForTrip($a);
    $guideB = TripGuide::ensureForTrip($b);

    expect($guideA->id)->not->toBe($guideB->id)->and($guideA->bus_unit_id)->toBe($busA->id)->and($guideB->bus_unit_id)->toBe($busB->id);

    $tabs = srcTabs($this, $day->copy()->startOfDay());
    expect($tabs)->toHaveCount(2)->and($tabs->pluck('color')->all())->toEqualCanonicalizing(['orange', 'purple']);
});

test('the same unit on the same route and day is refused as a duplicate; another unit to the same place is fine', function () {
    $busA = srcBus('A');
    $busB = srcBus('B');
    $day = now()->addDays(2)->toDateString();
    $existing = srcTrip($busA, ['day' => $day]);

    // same route + same unit + same day → duplicate unit
    expect(LandingRoute::busUnitConflict('SLP', 'CDMX', $busA->id, $day, null)?->id)->toBe($existing->id);
    // same route, ANOTHER unit → allowed
    expect(LandingRoute::busUnitConflict('SLP', 'CDMX', $busB->id, $day, null))->toBeNull();
    // same unit, another day → allowed
    expect(LandingRoute::busUnitConflict('SLP', 'CDMX', $busA->id, now()->addDays(3)->toDateString(), null))->toBeNull();
    // same unit on ANOTHER route that day → still a conflict (as before)
    expect(LandingRoute::busUnitConflict('SLP', 'GDL', $busA->id, $day, null)?->id)->toBe($existing->id);
    // re-saving the trip itself never conflicts with itself
    expect(LandingRoute::busUnitConflict('SLP', 'CDMX', $busA->id, $day, null, $existing->id))->toBeNull();
});

test('a recurring template and its own dated instance are not duplicates of each other, two templates are', function () {
    $bus = srcBus('A');
    $monday = now()->next(Carbon::MONDAY)->toDateString();
    $template = srcTrip($bus, ['day' => null, 'recurring_weekdays' => [1]]);

    // the dated instance of that template (what the daily activation creates)
    expect(LandingRoute::busUnitConflict('SLP', 'CDMX', $bus->id, $monday, null))->toBeNull();
    // a second recurring trip on the same route/unit/weekday IS a duplicate
    expect(LandingRoute::busUnitConflict('SLP', 'CDMX', $bus->id, null, [1, 2])?->id)->toBe($template->id);
});

test('creating a trip from Viajes with the same unit twice on a day shows the duplicate-unit error', function () {
    $bus = srcBus('A');
    $day = now()->addDays(4)->toDateString();
    srcTrip($bus, ['day' => $day]);

    $this->actingAs(srcAdmin())->post(route('admin.viajes.store'), [
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'day' => $day,
        'boarding_outbound_legend' => 'Terminal', 'boarding_return_legend' => 'Terminal',
    ])->assertSessionHas('error');

    expect(LandingRoute::count())->toBe(1);
});

test('a manual guide can be deleted from its own page', function () {
    $bus = srcBus('A');
    $guide = TripGuide::create([
        'from' => 'SLP', 'to' => 'CDMX', 'bus_unit_id' => $bus->id, 'source' => 'manual',
        'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
    ]);
    $admin = srcAdmin();

    $this->actingAs($admin)->get(route('admin.guias.show', $guide))->assertOk()->assertSee('Eliminar guía');
    $this->actingAs($admin)->delete(route('admin.guias.destroy', $guide))->assertRedirect(route('admin.guias.index'));

    expect(TripGuide::count())->toBe(0);
});
