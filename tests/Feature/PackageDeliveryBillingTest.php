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
