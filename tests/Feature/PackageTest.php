<?php

use App\Mail\PackageTrackingMail;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\PackageQrBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function paqueteriaUser(): User
{
    return User::factory()->create(['role' => User::ROLE_PAQUETERIA, 'email_verified_at' => now()]);
}

test('superadmin can generate a batch of unassigned QR packages', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.paqueteria.qr.store'), ['qty' => 5]);

    $response->assertOk();
    expect(Package::count())->toBe(5);
    expect(Package::pluck('status')->unique()->all())->toBe([Package::STATUS_SIN_ASIGNAR]);
    expect(Package::pluck('tracking_code')->unique())->toHaveCount(5);
});

test('generating a QR batch saves a PDF on disk, organized by date, and it can be downloaded', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $this->actingAs($admin)->post(route('admin.paqueteria.qr.store'), ['qty' => 3]);

    expect(PackageQrBatch::count())->toBe(1);
    $batch = PackageQrBatch::first();
    expect($batch->qty)->toBe(3);
    expect($batch->generated_by)->toBe($admin->id);
    expect($batch->pdf_path)->toStartWith('paqueteria/qr-lotes/'.now()->format('Y/m').'/');
    Storage::disk('local')->assertExists($batch->pdf_path);

    $response = $this->actingAs($admin)->get(route('admin.paqueteria.qr.batches.download', $batch));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('a failed PDF render does not prevent the QR batch itself from being created', function () {
    Storage::shouldReceive('disk')->andThrow(new RuntimeException('disk unavailable'));
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.paqueteria.qr.store'), ['qty' => 2]);

    $response->assertOk();
    expect(Package::count())->toBe(2);
    expect(PackageQrBatch::count())->toBe(0);
});

test('generating a new QR batch leaves leftover unassigned codes from a previous batch untouched', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $leftoverUnassigned = Package::factory()->create();

    $this->actingAs($admin)->post(route('admin.paqueteria.qr.store'), ['qty' => 4]);

    expect(Package::find($leftoverUnassigned->id))->not->toBeNull();
    expect(Package::status(Package::STATUS_SIN_ASIGNAR)->count())->toBe(5);
});

test('generating a new QR batch never deletes a package that has ever been registered, regardless of its status', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $survivors = collect([
        Package::STATUS_RECOLECTADO,
        Package::STATUS_EN_RECORRIDO,
        Package::STATUS_ENTREGADO,
        Package::STATUS_NO_ENTREGADO,
    ])->map(fn ($status) => Package::factory()->create(['status' => $status]));

    $this->actingAs($admin)->post(route('admin.paqueteria.qr.store'), ['qty' => 1]);

    $survivors->each(fn ($package) => expect(Package::find($package->id))->not->toBeNull());
});

test('the paqueteria dashboard separates active packages from the permanent history', function () {
    $staff = paqueteriaUser();
    $active = Package::factory()->create(['status' => Package::STATUS_EN_RECORRIDO]);
    $delivered = Package::factory()->create(['status' => Package::STATUS_ENTREGADO]);

    $activosResponse = $this->actingAs($staff)->get(route('admin.paqueteria', ['tab' => 'activos']));
    $activosResponse->assertSee($active->tracking_code);
    $activosResponse->assertDontSee($delivered->tracking_code);

    $historialResponse = $this->actingAs($staff)->get(route('admin.paqueteria', ['tab' => 'historial']));
    $historialResponse->assertSee($delivered->tracking_code);
    $historialResponse->assertDontSee($active->tracking_code);
});

test('staff can delete an unassigned QR code one at a time', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->create();

    $response = $this->actingAs($staff)->delete(route('admin.paqueteria.paquetes.destroy', $package));

    $response->assertRedirect(route('admin.paqueteria'));
    expect(Package::find($package->id))->toBeNull();
});

test('a package that has already been registered cannot be deleted', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->collected()->create();

    $response = $this->actingAs($staff)->delete(route('admin.paqueteria.paquetes.destroy', $package));

    $response->assertStatus(409);
    expect(Package::find($package->id))->not->toBeNull();
});

test('paqueteria staff can register a single package and the client receives a tracking email', function () {
    Storage::fake('local');
    Mail::fake();
    $staff = paqueteriaUser();
    $package = Package::factory()->create();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.assign', $package), [
        'client_name' => 'Juan Pérez',
        'client_email' => 'juan@example.com',
        'price' => 250,
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
    ]);

    $response->assertRedirect();
    $package->refresh();

    expect($package->status)->toBe(Package::STATUS_RECOLECTADO);
    expect($package->collected_by)->toBe($staff->id);
    expect((float) $package->price)->toBe(250.0);
    expect($package->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($package->photo_path);

    Mail::assertSent(PackageTrackingMail::class, function ($mail) use ($package) {
        return $mail->trackingCode === $package->tracking_code && ! $mail->isGroup;
    });
});

test('registering a package without an evidence photo fails validation', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();
    $package = Package::factory()->create();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.assign', $package), [
        'client_name' => 'Juan Pérez',
        'client_email' => 'juan@example.com',
        'price' => 250,
    ]);

    $response->assertSessionHasErrors('photo');
    expect($package->fresh()->status)->toBe(Package::STATUS_SIN_ASIGNAR);
});

test('staff can bundle multiple scanned packages into one group with a shared price', function () {
    Storage::fake('local');
    Mail::fake();
    $staff = paqueteriaUser();
    $first = Package::factory()->create();
    $second = Package::factory()->create();
    $third = Package::factory()->create();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.assign', $first), [
        'client_name' => 'María López',
        'client_email' => 'maria@example.com',
        'price' => 500,
        'photo' => UploadedFile::fake()->image('evidencia-1.jpg'),
        'codes' => [$second->tracking_code, $third->tracking_code],
        'photos' => [UploadedFile::fake()->image('evidencia-2.jpg'), UploadedFile::fake()->image('evidencia-3.jpg')],
    ]);

    $response->assertRedirect();

    expect(PackageGroup::count())->toBe(1);
    $group = PackageGroup::first();
    expect((float) $group->total_price)->toBe(500.0);

    foreach ([$first, $second, $third] as $package) {
        $package->refresh();
        expect($package->package_group_id)->toBe($group->id);
        expect($package->price)->toBeNull();
        expect($package->status)->toBe(Package::STATUS_RECOLECTADO);
        expect($package->photo_path)->not->toBeNull();
        Storage::disk('local')->assertExists($package->photo_path);
    }

    Mail::assertSent(PackageTrackingMail::class, 1);
    Mail::assertSent(PackageTrackingMail::class, function ($mail) use ($group) {
        return $mail->trackingCode === $group->tracking_code && $mail->isGroup && $mail->packageCount === 3;
    });
});

test('bundling without a photo for every extra package fails validation', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();
    $first = Package::factory()->create();
    $second = Package::factory()->create();
    $third = Package::factory()->create();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.assign', $first), [
        'client_name' => 'María López',
        'client_email' => 'maria@example.com',
        'price' => 500,
        'photo' => UploadedFile::fake()->image('evidencia-1.jpg'),
        'codes' => [$second->tracking_code, $third->tracking_code],
        'photos' => [UploadedFile::fake()->image('evidencia-2.jpg')],
    ]);

    $response->assertStatus(422);
    expect(PackageGroup::count())->toBe(0);
    expect($first->fresh()->status)->toBe(Package::STATUS_SIN_ASIGNAR);
});

test('assigning an already-collected code into a bundle fails', function () {
    Storage::fake('local');
    Mail::fake();
    $staff = paqueteriaUser();
    $first = Package::factory()->create();
    $alreadyCollected = Package::factory()->collected()->create();

    $response = $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.assign', $first), [
        'client_name' => 'Carlos Ruiz',
        'client_email' => 'carlos@example.com',
        'price' => 300,
        'photo' => UploadedFile::fake()->image('evidencia.jpg'),
        'codes' => [$alreadyCollected->tracking_code],
        'photos' => [UploadedFile::fake()->image('evidencia-2.jpg')],
    ]);

    $response->assertStatus(422);
    expect(PackageGroup::count())->toBe(0);
    expect($first->fresh()->status)->toBe(Package::STATUS_SIN_ASIGNAR);
    Mail::assertNothingSent();
});

test('staff can view a package evidence photo but a cliente-role user cannot', function () {
    Storage::fake('local');
    $staff = paqueteriaUser();
    $path = 'paqueteria/evidencias/2026/08/MP-TEST-1.jpg';
    Storage::disk('local')->put($path, UploadedFile::fake()->image('x.jpg')->get());
    $package = Package::factory()->create(['status' => Package::STATUS_RECOLECTADO, 'photo_path' => $path]);

    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.photo', $package))->assertOk();

    $cliente = User::factory()->create(['role' => User::ROLE_CLIENTE, 'email_verified_at' => now()]);
    $this->actingAs($cliente)->get(route('admin.paqueteria.paquetes.photo', $package))->assertForbidden();
});

test('viewing the photo of a package with no photo on file returns 404', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->create();

    $this->actingAs($staff)->get(route('admin.paqueteria.paquetes.photo', $package))->assertNotFound();
});

test('staff can move a package through its status lifecycle', function () {
    $staff = paqueteriaUser();
    $package = Package::factory()->collected()->create();

    $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.update-status', $package), ['status' => 'en_recorrido']);
    expect($package->fresh()->status)->toBe('en_recorrido');
    expect($package->fresh()->delivered_at)->toBeNull();

    $this->actingAs($staff)->post(route('admin.paqueteria.paquetes.update-status', $package), ['status' => 'entregado']);
    expect($package->fresh()->status)->toBe('entregado');
    expect($package->fresh()->delivered_at)->not->toBeNull();
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

test('the public tracking page finds an individual package by code', function () {
    $package = Package::factory()->collected()->create();

    $response = $this->get(route('paqueteria.rastreo', ['code' => $package->tracking_code]));

    $response->assertOk();
    $response->assertSee($package->tracking_code);
});

test('the public tracking page finds a group by code and lists its member packages with their own statuses', function () {
    $group = PackageGroup::factory()->create();
    $member1 = Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_EN_RECORRIDO]);
    $member2 = Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_ENTREGADO]);

    $response = $this->get(route('paqueteria.rastreo', ['code' => $group->tracking_code]));

    $response->assertOk();
    $response->assertSee($member1->tracking_code);
    $response->assertSee($member2->tracking_code);
});

test('a shipment reads as en_recorrido as soon as one of its packages does, even if the rest are still recolectado', function () {
    $group = PackageGroup::factory()->create();
    Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_RECOLECTADO]);
    Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_RECOLECTADO]);
    Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_EN_RECORRIDO]);

    expect(Package::furthestStatus($group->fresh('packages')->packages))->toBe(Package::STATUS_EN_RECORRIDO);
});

test('a shipment where every package failed reads as no_entregado', function () {
    $group = PackageGroup::factory()->create();
    Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_NO_ENTREGADO]);
    Package::factory()->create(['package_group_id' => $group->id, 'status' => Package::STATUS_NO_ENTREGADO]);

    expect(Package::furthestStatus($group->fresh('packages')->packages))->toBe(Package::STATUS_NO_ENTREGADO);
});

test('the public tracking page shows a friendly message for an unknown code', function () {
    $response = $this->get(route('paqueteria.rastreo', ['code' => 'MP-NOPE1234']));

    $response->assertOk();
    $response->assertSee('No encontramos ese código');
});
