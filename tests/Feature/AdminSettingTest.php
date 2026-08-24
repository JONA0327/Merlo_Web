<?php

use App\Models\Setting;
use App\Models\User;

test('superadmin can update the whatsapp number and social links', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->put(route('admin.configuraciones.update'), [
        'whatsapp_number' => '52 1 55 1234 5678',
        'facebook_url' => 'https://facebook.com/merlotransportes',
        'instagram_url' => 'https://instagram.com/merlotransportes',
    ]);

    $response->assertRedirect(route('admin.configuraciones'));

    $setting = Setting::current();
    expect($setting->whatsapp_number)->toBe('52 1 55 1234 5678');
    expect($setting->whatsappDigits())->toBe('5215512345678');
    expect($setting->facebook_url)->toBe('https://facebook.com/merlotransportes');
    expect($setting->instagram_url)->toBe('https://instagram.com/merlotransportes');
});

test('a non-superadmin cannot reach the settings page', function () {
    $staff = User::factory()->create(['role' => User::ROLE_PAQUETERIA, 'email_verified_at' => now()]);

    $this->actingAs($staff)->get(route('admin.configuraciones'))->assertForbidden();
});

test('an invalid social link fails validation and keeps the previous value', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    Setting::current()->update(['facebook_url' => 'https://facebook.com/original']);

    $response = $this->actingAs($admin)->put(route('admin.configuraciones.update'), [
        'facebook_url' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('facebook_url');
    expect(Setting::current()->facebook_url)->toBe('https://facebook.com/original');
});

test('the homepage shows the WhatsApp quote button once a number is configured, and hides social icons that are not set', function () {
    Setting::current()->update([
        'whatsapp_number' => '5215512345678',
        'facebook_url' => null,
        'instagram_url' => 'https://instagram.com/merlotransportes',
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Enviar cotización por WhatsApp');
    $response->assertSee('https://wa.me/5215512345678', false);
    $response->assertSee('https://instagram.com/merlotransportes', false);
});

test('the homepage falls back to an email hint when no WhatsApp number is configured', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('Enviar cotización por WhatsApp');
    $response->assertSee('El envío por WhatsApp aún no está disponible');
});
