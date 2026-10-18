<?php

use App\Models\LandingRoute;
use App\Models\TripCorte;
use App\Models\TripCorteLine;
use App\Models\User;
use App\Services\TripCorteService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['*' => Http::response(['data' => []], 201)]);
    config(['services.control_reportes.url' => 'http://control.test', 'services.control_reportes.token' => 'tok']);
    $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $this->trip = LandingRoute::create(['from' => 'SLP', 'to' => 'CDMX', 'day' => today()->addDay(), 'available_seats' => 10, 'is_active' => true]);
});

test('a concept is saved with quantity times unit amount and can be marked informative', function () {
    $this->actingAs($this->admin)->post(route('admin.viajes.corte.lines.store', $this->trip), [
        'kind' => 'gasto', 'concept' => 'Sueldo salida', 'quantity' => 2, 'unit_amount' => 2700, 'note' => 'ref 1', 'counts' => 1,
    ])->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.viajes.corte.lines.store', $this->trip), [
        'kind' => 'gasto', 'concept' => 'Daniel paquetería', 'unit_amount' => 6700,
    ])->assertRedirect();

    $sueldo = TripCorteLine::where('concept', 'Sueldo salida')->first();
    expect((float) $sueldo->amount)->toBe(5400.0)->and($sueldo->counts)->toBeTrue();

    $daniel = TripCorteLine::where('concept', 'Daniel paquetería')->first();
    expect((float) $daniel->quantity)->toBe(1.0)->and((float) $daniel->amount)->toBe(6700.0)->and($daniel->counts)->toBeFalse();
});

test('a concept needs a kind, a concept and a positive amount, and only its own trip can remove it', function () {
    $this->actingAs($this->admin)->post(route('admin.viajes.corte.lines.store', $this->trip), ['kind' => 'otro', 'concept' => '', 'unit_amount' => 0])
        ->assertSessionHasErrors(['kind', 'concept', 'unit_amount']);
    expect(TripCorteLine::count())->toBe(0);

    $line = TripCorteLine::create(['landing_route_id' => $this->trip->id, 'kind' => 'gasto', 'concept' => 'X', 'quantity' => 1, 'unit_amount' => 10, 'amount' => 10, 'counts' => true]);
    $other = LandingRoute::create(['from' => 'A', 'to' => 'B', 'day' => today()->addDays(2), 'available_seats' => 1, 'is_active' => true]);

    $this->actingAs($this->admin)->delete(route('admin.viajes.corte.lines.destroy', [$other, $line]))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.viajes.corte.lines.destroy', [$this->trip, $line]))->assertRedirect();
    expect(TripCorteLine::count())->toBe(0);
});

test('sending the corte posts the concepts, cash and sales breakdown to Control, and flags later changes', function () {
    TripCorteLine::create(['landing_route_id' => $this->trip->id, 'kind' => 'gasto', 'concept' => 'Casetas', 'quantity' => 2, 'unit_amount' => 1900, 'amount' => 3800, 'note' => '5840/0007', 'counts' => true]);
    TripCorteLine::create(['landing_route_id' => $this->trip->id, 'kind' => 'ingreso', 'concept' => 'Por cobrar', 'quantity' => 4, 'unit_amount' => 400, 'amount' => 1600, 'counts' => false]);

    $this->actingAs($this->admin)->post(route('admin.viajes.corte', $this->trip), ['contrato_id' => 4, 'cash_amount' => 11200])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/merlo/cortes') && $r['contrato_id'] === 4 && $r['efectivo'] == 11200
        && count($r['lines']) === 2 && $r['lines'][0]['concept'] === 'Casetas' && $r['lines'][0]['amount'] == 3800 && $r['lines'][0]['counts'] === true
        && $r['lines'][1]['counts'] === false && array_key_exists('by_type', $r['desglose']) && array_key_exists('por_cobrar_amount', $r['desglose']));

    $corte = TripCorte::where('landing_route_id', $this->trip->id)->first();
    expect((float) $corte->cash_amount)->toBe(11200.0)->and($corte->sent_at)->not->toBeNull();

    $service = app(TripCorteService::class);
    expect($corte->lines_signature)->toBe($service->signature($service->lines($this->trip), $corte->fresh()));

    TripCorteLine::first()->delete();
    expect($corte->lines_signature)->not->toBe($service->signature($service->lines($this->trip), $corte->fresh()));
});

test('saving keeps the cash and external sales without sending anything to Control', function () {
    $this->actingAs($this->admin)->post(route('admin.viajes.corte.save', $this->trip), ['cash_amount' => 500, 'external_seats' => 0, 'external_amount' => 0])
        ->assertSessionHas('success');

    Http::assertNothingSent();
    expect((float) TripCorte::where('landing_route_id', $this->trip->id)->value('cash_amount'))->toBe(500.0);
    expect(TripCorte::where('landing_route_id', $this->trip->id)->value('sent_at'))->toBeNull();
});

test('the corte page renders and the guide panel links to it', function () {
    $this->actingAs($this->admin)->get(route('admin.viajes.corte.show', $this->trip))->assertOk()->assertSee('Corte del viaje');
});
