<?php

use App\Models\Package;
use App\Models\PackageClient;
use App\Models\PackageGroup;
use App\Models\PackagePayment;
use App\Models\User;
use App\Services\PackageCorteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    \App\Models\Setting::current()->update([
        'evolution_api_url' => 'https://example.test',
        'evolution_api_key' => 'key',
        'evolution_instance' => 'test',
    ]);
    $this->staff = User::factory()->create(['role' => User::ROLE_PAQUETERIA, 'email_verified_at' => now()]);
});

function groupOfThree(float $total = 300): array
{
    $group = PackageGroup::factory()->create(['client_name' => 'Ana', 'phone' => '4445551212', 'total_price' => $total, 'amount_paid' => 0]);
    $packages = collect([1, 2, 3])->map(fn ($n) => Package::factory()->create([
        'folio_number' => $n, 'status' => Package::STATUS_RECOLECTADO, 'package_group_id' => $group->id,
    ]));

    return [$group, $packages];
}

test('a partial delivery marks only the chosen folios and leaves the rest pending', function () {
    [$group, $packages] = groupOfThree();

    $this->actingAs($this->staff)->post(route('admin.paqueteria.paquetes.deliver', $packages[0]), [
        'package_ids' => [$packages[0]->id], 'amount_paid' => 100,
    ])->assertRedirect();

    expect($packages[0]->fresh()->status)->toBe('entregado');
    expect($packages[1]->fresh()->status)->toBe('recolectado');
    expect($packages[2]->fresh()->status)->toBe('recolectado');
    expect((float) $group->fresh()->amount_paid)->toBe(100.0);
    expect($group->fresh()->pendingAmount())->toBe(200.0);
    Http::assertSent(fn ($r) => str_contains((string) $r['text'], 'F-000002, F-000003') && str_contains((string) $r['text'], '200.00'));
});

test('the cost can be captured at delivery when it was not set at registration', function () {
    $package = Package::factory()->collected()->create(['folio_number' => 7, 'price' => null]);

    $this->actingAs($this->staff)->post(route('admin.paqueteria.paquetes.deliver', $package), [
        'package_ids' => [$package->id], 'price' => 150, 'amount_paid' => 50,
    ]);

    expect((float) $package->fresh()->price)->toBe(150.0);
    expect($package->fresh()->pendingAmount())->toBe(100.0);
});

test('charging without a cost, or more than the balance, is rejected and nothing changes', function () {
    $noCost = Package::factory()->collected()->create(['folio_number' => 8, 'price' => null]);
    $this->actingAs($this->staff)->post(route('admin.paqueteria.paquetes.deliver', $noCost), ['package_ids' => [$noCost->id], 'amount_paid' => 10])
        ->assertSessionHasErrors('price');
    expect($noCost->fresh()->status)->toBe('recolectado');

    $priced = Package::factory()->collected()->create(['folio_number' => 9, 'price' => 100]);
    $this->actingAs($this->staff)->post(route('admin.paqueteria.paquetes.deliver', $priced), ['package_ids' => [$priced->id], 'amount_paid' => 999])
        ->assertSessionHasErrors('amount_paid');
    expect(PackagePayment::count())->toBe(0);
});

test('a pending balance can be paid later from the client page and is capped at the balance', function () {
    [$group, $packages] = groupOfThree();
    $client = PackageClient::forRegistration('Ana', '444 555 1212');
    $group->update(['package_client_id' => $client->id]);
    $packages->each->update(['status' => Package::STATUS_ENTREGADO]);
    $group->registerPayment(100, $this->staff->id);

    expect($client->pendingAmount())->toBe(200.0);

    $this->actingAs($this->staff)->post(route('admin.paqueteria.clientes.pay', $client), ['unit' => "group:{$group->id}", 'amount' => 500])
        ->assertSessionHasErrors('amount');
    $this->actingAs($this->staff)->post(route('admin.paqueteria.clientes.pay', $client), ['unit' => "group:{$group->id}", 'amount' => 200]);

    expect($client->fresh()->pendingAmount())->toBe(0.0);
    expect(PackagePayment::count())->toBe(2);
});

test('clients are matched by phone digits, whatever the formatting', function () {
    $a = PackageClient::forRegistration('Juan', '444 123 4567');
    $b = PackageClient::forRegistration('Juan Perez', '+52 (444) 123-4567');

    expect($b->id)->toBe($a->id);
    expect(PackageClient::count())->toBe(1);
    expect($b->fresh()->name)->toBe('Juan Perez');
    expect($b->phone_formatted)->toBe('444 123 4567');
});

test('the daily corte sums that day payments and posts them to Control', function () {
    [$group] = groupOfThree();
    $group->registerPayment(100, $this->staff->id);
    $group->registerPayment(50, $this->staff->id);
    PackagePayment::query()->where('amount', 50)->update(['paid_at' => Carbon::yesterday()]);

    config(['services.control_reportes.url' => 'http://control.test', 'services.control_reportes.token' => 'tok']);

    $corte = app(PackageCorteService::class)->confirm(today(), $this->staff->id);
    $corte = app(PackageCorteService::class)->confirm(today(), $this->staff->id); // reenviar no duplica

    expect((float) $corte->total_amount)->toBe(100.0);
    expect(\App\Models\PackageCorte::count())->toBe(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/merlo/cortes-paqueteria') && $r['total_amount'] == 100.0 && $r['payments_count'] == 1);
});

test('the daily corte sends that day expenses to Control and only that day expenses', function () {
    config(['services.control_reportes.url' => 'http://control.test', 'services.control_reportes.token' => 'tok']);

    $this->actingAs($this->staff)->post(route('admin.paqueteria.corte.expenses.store'), ['fecha' => today()->toDateString(), 'concept' => 'Gasolina', 'amount' => 150.5])->assertRedirect();
    $this->actingAs($this->staff)->post(route('admin.paqueteria.corte.expenses.store'), ['fecha' => Carbon::yesterday()->toDateString(), 'concept' => 'De ayer', 'amount' => 40])->assertRedirect();

    $corte = app(PackageCorteService::class)->confirm(today(), $this->staff->id);

    expect((float) $corte->expenses_amount)->toBe(150.5);
    expect($corte->expenses_count)->toBe(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/merlo/cortes-paqueteria')
        && count($r['expenses']) === 1 && $r['expenses'][0]['concept'] === 'Gasolina' && $r['expenses'][0]['amount'] == 150.5);
});

test('an expense needs a concept and a positive amount, and can be removed', function () {
    $this->actingAs($this->staff)->post(route('admin.paqueteria.corte.expenses.store'), ['fecha' => today()->toDateString(), 'concept' => '', 'amount' => 0])
        ->assertSessionHasErrors(['concept', 'amount']);
    expect(\App\Models\PackageExpense::count())->toBe(0);

    $this->actingAs($this->staff)->post(route('admin.paqueteria.corte.expenses.store'), ['fecha' => today()->toDateString(), 'concept' => 'Comida', 'amount' => 90]);
    $expense = \App\Models\PackageExpense::first();
    $this->actingAs($this->staff)->delete(route('admin.paqueteria.corte.expenses.destroy', $expense))->assertRedirect();

    expect(\App\Models\PackageExpense::count())->toBe(0);
});

test('the paqueteria main page lists clients, not folios, and filters by packages in progress', function () {
    $ana = PackageClient::forRegistration('Ana Lopez', '444 111 2222');
    $beto = PackageClient::forRegistration('Beto Ruiz', '444 333 4444');
    Package::factory()->create(['folio_number' => 701, 'status' => Package::STATUS_RECOLECTADO, 'package_client_id' => $ana->id]);
    Package::factory()->create(['folio_number' => 702, 'status' => Package::STATUS_ENTREGADO, 'package_client_id' => $beto->id]);
    Package::factory()->create(['folio_number' => 703, 'status' => Package::STATUS_SIN_ASIGNAR]);

    $this->actingAs($this->staff)->get(route('admin.paqueteria'))
        ->assertOk()->assertSee('Ana Lopez')->assertSee('Beto Ruiz')->assertSee('444 111 2222')->assertDontSee('F-000703');

    $this->actingAs($this->staff)->get(route('admin.paqueteria', ['filtro' => 'en_curso']))
        ->assertSee('Ana Lopez')->assertDontSee('Beto Ruiz');

    // El listado de folios sigue disponible como vista aparte.
    $this->actingAs($this->staff)->get(route('admin.paqueteria', ['vista' => 'folios']))->assertSee('F-000703');
});

test('searching clients by name does not match everyone, and a folio jumps to its package', function () {
    PackageClient::forRegistration('Ana Lopez', '444 111 2222');
    PackageClient::forRegistration('Beto Ruiz', '444 333 4444');
    $package = Package::factory()->create(['folio_number' => 710, 'status' => Package::STATUS_RECOLECTADO]);

    $this->actingAs($this->staff)->get(route('admin.paqueteria', ['q' => 'Beto']))->assertSee('Beto Ruiz')->assertDontSee('Ana Lopez');
    $this->actingAs($this->staff)->get(route('admin.paqueteria', ['q' => 'F-000710']))->assertRedirect(route('admin.paqueteria.paquetes.show', $package));
});
