<?php

use App\Models\Package;
use App\Models\PackageFolioSequence;
use App\Models\PackageGroup;
use App\Models\PackageQrBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function paqueteriaUser(): User
{
    return User::factory()->create(['role' => User::ROLE_PAQUETERIA, 'email_verified_at' => now()]);
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    \App\Models\Setting::current()->update([
        'evolution_api_url' => 'https://example.test',
        'evolution_api_key' => 'test-key',
        'evolution_instance' => 'test-instance',
    ]);
});

test('printing a folio guide reserves a contiguous range of blank folios', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 5]);

    $response->assertOk();
    expect(Package::count())->toBe(5);
    expect(Package::pluck('status')->unique()->all())->toBe([Package::STATUS_SIN_ASIGNAR]);
    expect(Package::pluck('folio_number')->sort()->values()->all())->toBe([1, 2, 3, 4, 5]);
    expect(Package::pluck('tracking_code')->unique())->toHaveCount(5);
});

test('a second folio guide continues the same global series, never restarting', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 5]);
    $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 3]);

    expect(Package::pluck('folio_number')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
});

test('printing a folio guide saves a PDF on disk, organized by date, and it can be downloaded', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 3]);

    expect(PackageQrBatch::count())->toBe(1);
    $batch = PackageQrBatch::first();
    expect($batch->qty)->toBe(3);
    expect($batch->generated_by)->toBe($admin->id);
    expect($batch->pdf_path)->toStartWith('paqueteria/folio-lotes/'.now()->format('Y/m').'/');
    Storage::disk('local')->assertExists($batch->pdf_path);

    $response = $this->actingAs($admin)->get(route('admin.paqueteria.qr.batches.download', $batch));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('a failed PDF render does not prevent the folio batch itself from being created', function () {
    Storage::shouldReceive('disk')->andThrow(new RuntimeException('disk unavailable'));
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 2]);

    $response->assertOk();
    expect(Package::count())->toBe(2);
    expect(PackageQrBatch::count())->toBe(0);
});

test('printing a new folio guide never touches a package that has ever been registered', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $survivors = collect([
        Package::STATUS_RECOLECTADO,
        Package::STATUS_EN_RECORRIDO,
        Package::STATUS_ENTREGADO,
        Package::STATUS_NO_ENTREGADO,
    ])->map(fn ($status) => Package::factory()->create(['status' => $status]));

    $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 1]);

    $survivors->each(fn ($package) => expect(Package::find($package->id))->not->toBeNull());
});

test('the paqueteria dashboard separates active packages from the permanent history', function () {
    $staff = paqueteriaUser();
    $active = Package::factory()->create(['status' => Package::STATUS_EN_RECORRIDO, 'folio_number' => 501]);
    $delivered = Package::factory()->create(['status' => Package::STATUS_ENTREGADO, 'folio_number' => 502]);

    $activosResponse = $this->actingAs($staff)->get(route('admin.paqueteria', ['tab' => 'activos']));
    $activosResponse->assertSee($active->folio_label);
    $activosResponse->assertDontSee($delivered->folio_label);

    $historialResponse = $this->actingAs($staff)->get(route('admin.paqueteria', ['tab' => 'historial']));
    $historialResponse->assertSee($delivered->folio_label);
    $historialResponse->assertDontSee($active->folio_label);
});

test('staff can delete an unassigned folio one at a time', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->create(['folio_number' => 1]);

    $response = $this->actingAs($staff)->delete(route('admin.paqueteria.paquetes.destroy', $package));

    $response->assertRedirect(route('admin.paqueteria'));
    expect(Package::find($package->id))->toBeNull();
});

test('a folio that has already been registered cannot be deleted', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->collected()->create();

    $response = $this->actingAs($staff)->delete(route('admin.paqueteria.paquetes.destroy', $package));

    $response->assertStatus(409);
    expect(Package::find($package->id))->not->toBeNull();
});

test('registering a single package assigns it a folio and sends a WhatsApp notice', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Juan Pérez',
        'qty' => 1,
        'phone' => '4444416578',
        'price' => 250,
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $response->assertRedirect();
    expect(Package::count())->toBe(1);
    $package = Package::first();

    expect($package->status)->toBe(Package::STATUS_RECOLECTADO);
    expect($package->folio_number)->toBe(1);
    expect($package->collected_by)->toBe($staff->id);
    expect((float) $package->price)->toBe(250.0);
    expect($package->phone)->toBe('4444416578');
    expect($package->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($package->photo_path);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/')
        && str_contains($request['text'], 'F-000001'));
});

test('registering several packages bundles them into one group with contiguous folios and a shared photo', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'María López',
        'qty' => 5,
        'phone' => '4444416578',
        'price' => 500,
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $response->assertRedirect();

    expect(PackageGroup::count())->toBe(1);
    $group = PackageGroup::first();
    expect((float) $group->total_price)->toBe(500.0);
    expect($group->phone)->toBe('4444416578');
    expect($group->photo_path)->not->toBeNull();

    $members = $group->packages()->orderBy('folio_number')->get();
    expect($members)->toHaveCount(5);
    expect($members->pluck('folio_number')->all())->toBe([1, 2, 3, 4, 5]);

    foreach ($members as $package) {
        expect($package->price)->toBeNull();
        expect($package->phone)->toBeNull();
        expect($package->photo_path)->toBeNull();
        expect($package->status)->toBe(Package::STATUS_RECOLECTADO);
        expect($package->displayPrice())->toBe('500.00');
        expect($package->displayPhone())->toBe('4444416578');
        expect($package->displayPhotoPath())->not->toBeNull();
    }

    Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/')
        && str_contains($request['text'], 'F-000001 a F-000005'));
});

test('registering claims already-printed blank folios before minting new ones, staying contiguous', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $staff = paqueteriaUser();

    // Pre-print 3 blank folios (1-3), then register 5 — should claim 1-3
    // and mint 4-5 fresh, for a contiguous 1-5 overall.
    $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 3]);

    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Carlos Ruiz',
        'qty' => 5,
        'phone' => '4444416578',
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $group = PackageGroup::sole();
    $folios = $group->packages()->orderBy('folio_number')->pluck('folio_number')->all();
    expect($folios)->toBe([1, 2, 3, 4, 5]);
    // Total was left blank — stays optional, no total line forced on anyone.
    expect($group->total_price)->toBeNull();
});

test('a leftover pre-printed folio is still claimed by a later, unrelated registration', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $staff = paqueteriaUser();

    $this->actingAs($admin)->post(route('admin.paqueteria.folios.store'), ['qty' => 10]);
    // Only 2 of the 10 pre-printed folios get used right away.
    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Ana Torres',
        'qty' => 2,
        'phone' => '4444416578',
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    expect(Package::where('status', Package::STATUS_SIN_ASIGNAR)->count())->toBe(8);

    // A second, later registration picks up right where the first left off.
    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Luis Vega',
        'qty' => 3,
        'phone' => '4444416578',
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $secondGroup = PackageGroup::where('client_name', 'Luis Vega')->sole();
    expect($secondGroup->packages()->orderBy('folio_number')->pluck('folio_number')->all())->toBe([3, 4, 5]);
    expect(Package::where('status', Package::STATUS_SIN_ASIGNAR)->count())->toBe(5);
});

test('registering without a phone fails validation', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Juan Pérez',
        'qty' => 1,
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $response->assertSessionHasErrors('phone');
    expect(Package::count())->toBe(0);
});

test('registering without an evidence photo fails validation', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Juan Pérez',
        'qty' => 1,
        'phone' => '4444416578',
    ]);

    $response->assertSessionHasErrors('photo');
    expect(Package::count())->toBe(0);
});

test('registering still succeeds even when the WhatsApp send fails, and says so', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    $staff = paqueteriaUser();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Juan Pérez',
        'qty' => 1,
        'phone' => '4444416578',
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    expect(Package::count())->toBe(1);
    expect(Package::first()->status)->toBe(Package::STATUS_RECOLECTADO);
});

test('staff can view a package evidence photo but a cliente-role user cannot', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();
    $path = 'paqueteria/evidencias/2026/08/test.jpg';
    Storage::disk('local')->put($path, UploadedFile::fake()->image('x.jpg')->get());
    $package = Package::factory()->create(['status' => Package::STATUS_RECOLECTADO, 'folio_number' => 1, 'photo_path' => $path]);

    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.photo', $package))->assertOk();

    $cliente = User::factory()->create(['role' => User::ROLE_CLIENTE, 'email_verified_at' => now()]);
    $this->actingAs($cliente)->get(route('admin.paqueteria.paquetes.photo', $package))->assertForbidden();
});

test('viewing the photo of a package with no photo on file returns 404', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->create(['folio_number' => 1]);

    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.photo', $package))->assertNotFound();
});

test('staff can move a package through its status lifecycle', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->collected()->create(['folio_number' => 1]);

    $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.update-status', $package), ['status' => 'en_recorrido']);
    expect($package->fresh()->status)->toBe('en_recorrido');
    expect($package->fresh()->delivered_at)->toBeNull();

    // 'entregado' ya no se pone a mano: pasa por el formulario de entrega (cobro + aviso).
    $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.update-status', $package), ['status' => 'entregado'])->assertStatus(409);
    expect($package->fresh()->status)->toBe('en_recorrido');
});

test('looking up a package by its folio label redirects to its detail page', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->create(['folio_number' => 42]);

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.lookup'), ['code' => 'F-000042']);

    $response->assertRedirect(route('admin.paqueteria.paquetes.show', $package));
});

test('a cliente-role user cannot reach paqueteria routes', function () {
    $cliente = User::factory()->create(['role' => User::ROLE_CLIENTE, 'email_verified_at' => now()]);

    $this->actingAs($cliente)->get(route('admin.paqueteria'))->assertForbidden();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get(route('admin.paqueteria'))->assertRedirect(route('login'));
});

test('a paqueteria-role user can reach admin.paqueteria but not admin.viajes', function () {
    $staff = paqueteriaUser();

    $this->actingAs($staff)->get(route('admin.paqueteria'))->assertOk();
    $this->actingAs($staff)->get(route('admin.viajes'))->assertForbidden();
});

test('a superadmin can reach both admin.paqueteria and admin.viajes', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('admin.paqueteria'))->assertOk();
    $this->actingAs($admin)->get(route('admin.viajes'))->assertOk();
});

test('the universal QR page renders an SVG QR code', function () {
    $staff = paqueteriaUser();

    $response = $this->actingAs($staff)->get(route('admin.paqueteria.qr-universal'));

    $response->assertOk();
    $response->assertSee('<svg', false);
});

test('PackageFolioSequence reserves contiguous, ever-increasing ranges', function () {
    [$firstStart, $firstEnd] = PackageFolioSequence::reserve(5);
    [$secondStart, $secondEnd] = PackageFolioSequence::reserve(3);

    expect([$firstStart, $firstEnd])->toBe([1, 5]);
    expect([$secondStart, $secondEnd])->toBe([6, 8]);
    expect(PackageFolioSequence::formatFolio($firstStart))->toBe('F-000001');
});

// ---------------------------------------------------------------------------
// Several evidence photos per registration
// ---------------------------------------------------------------------------

test('registering a single package with several photos keeps the first as photo_path and the rest as extra photos', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();

    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Juan Pérez', 'qty' => 1, 'phone' => '4444416578',
        'photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $package = Package::first();
    expect($package->photo_path)->not->toBeNull()
        ->and($package->extraPhotos)->toHaveCount(2)
        ->and($package->evidencePhotos())->toHaveCount(2);
    Storage::disk('local')->assertExists($package->photo_path);
    $package->extraPhotos->each(fn ($p) => Storage::disk('local')->assertExists($p->path));
});

test('registering several packages with several photos shares them through the group', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();

    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Ana', 'qty' => 3, 'phone' => '4444416578',
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $group = PackageGroup::first();
    expect($group->photo_path)->not->toBeNull()->and($group->extraPhotos)->toHaveCount(1);
    Package::all()->each(function (Package $p) use ($group) {
        expect($p->photo_path)->toBeNull()
            ->and($p->displayPhotoPath())->toBe($group->photo_path)
            ->and($p->evidencePhotos()->pluck('id')->all())->toBe($group->extraPhotos->pluck('id')->all());
    });
});

test('staff can open an extra photo; it is not served through another registration', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();
    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Uno', 'qty' => 1, 'phone' => '4444416578',
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]);
    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), [
        'client_name' => 'Dos', 'qty' => 1, 'phone' => '4444416578', 'photo' => UploadedFile::fake()->image('c.jpg'),
    ]);
    [$one, $two] = [Package::orderBy('id')->first(), Package::orderBy('id')->skip(1)->first()];
    $extra = $one->extraPhotos->first();

    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.extra-photo', [$one, $extra]))->assertOk();
    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.extra-photo', [$two, $extra]))->assertNotFound();

    $cliente = User::factory()->create(['role' => User::ROLE_CLIENTE, 'email_verified_at' => now()]);
    $this->actingAs($cliente)->get(route('admin.paqueteria.paquetes.extra-photo', [$one, $extra]))->assertForbidden();

    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.show', $one))->assertOk()
        ->assertSee(route('admin.paqueteria.paquetes.extra-photo', [$one, $extra]), false);
});

test('photo validation: at least one image, at most ten, images only', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();
    $base = ['client_name' => 'X', 'qty' => 1, 'phone' => '4444416578'];

    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), $base)->assertSessionHasErrors('photo');

    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), $base + [
        'photos' => array_map(fn ($i) => UploadedFile::fake()->image("$i.jpg"), range(1, 11)),
    ])->assertSessionHasErrors('photos');

    $this->actingAs($staff)->post(route('admin.paqueteria.registrar.store'), $base + [
        'photos' => [UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');

    expect(Package::count())->toBe(0);
});
