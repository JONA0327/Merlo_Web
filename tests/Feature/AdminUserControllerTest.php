<?php

use App\Mail\InternalAccountWelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('superadmin can create an internal paqueteria account and it receives a welcome email', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Ana Torres',
        'email' => 'ana@merlotransportes.com',
        'role' => 'paqueteria',
    ]);

    $response->assertRedirect(route('admin.usuarios.create'));

    $user = User::where('email', 'ana@merlotransportes.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('paqueteria');
    expect($user->email_verified_at)->not->toBeNull();

    Mail::assertSent(InternalAccountWelcomeMail::class, fn ($mail) => $mail->user->is($user));
});

test('a newly created internal account can set its password through the existing reset-code flow and log in', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now()]);
    $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Luis Gómez',
        'email' => 'luis@merlotransportes.com',
        'role' => 'paqueteria',
    ]);

    $user = User::where('email', 'luis@merlotransportes.com')->first();
    $code = '123456';
    $user->forceFill([
        'password_reset_code' => Hash::make($code),
        'password_reset_code_expires_at' => now()->addMinutes(15),
    ])->save();

    // The reset-password routes are guest-only — log the admin out first so
    // this simulates the new employee actually completing the flow.
    $this->post('/logout');

    $this->post(route('password.verify'), ['email' => $user->email, 'code' => $code])
        ->assertRedirect(route('password.reset', ['email' => $user->email]));

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-new-secure-password',
        'password_confirmation' => 'a-new-secure-password',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user->fresh());
});
