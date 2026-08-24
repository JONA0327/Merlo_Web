<?php

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password code can be requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use ($user) {
        return $mail->user->is($user) && preg_match('/^\d{6}$/', $mail->code) === 1;
    });

    $response->assertRedirect(route('password.reset', ['email' => $user->email]));
});

test('requesting a code does not reveal whether the email is registered', function () {
    Mail::fake();

    $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

    Mail::assertNothingSent();
    $response->assertRedirect(route('password.reset', ['email' => 'nobody@example.com']));
});

test('reset password screen shows the code step first', function () {
    $response = $this->get('/reset-password?email=test@example.com');

    $response->assertStatus(200);
    $response->assertViewHas('verified', false);
});

test('an incorrect code keeps the user on the code step', function () {
    Mail::fake();

    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    $response = $this->post('/reset-password/verify-code', [
        'email' => $user->email,
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');

    $getResponse = $this->get('/reset-password?email='.$user->email);
    $getResponse->assertViewHas('verified', false);
});

test('a valid code unlocks the new password step without changing the password yet', function () {
    Mail::fake();

    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    $code = null;
    Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $verifyResponse = $this->post('/reset-password/verify-code', [
        'email' => $user->email,
        'code' => $code,
    ]);

    $verifyResponse->assertRedirect(route('password.reset', ['email' => $user->email]));

    $getResponse = $this->get('/reset-password?email='.$user->email);
    $getResponse->assertViewHas('verified', true);

    $this->assertGuest();
});

test('password can be set once the code step was verified', function () {
    Mail::fake();

    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    $code = null;
    Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->post('/reset-password/verify-code', ['email' => $user->email, 'code' => $code]);

    $response = $this->post('/reset-password', [
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user->fresh());
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->password_reset_code)->toBeNull();
});

test('the password step cannot be reached by posting directly without verifying a code', function () {
    $user = User::factory()->create();

    $response = $this->post('/reset-password', [
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeFalse();
});

test('verifying a code for one account does not unlock the password step for another', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $this->post('/forgot-password', ['email' => $owner->email]);

    $code = null;
    Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->post('/reset-password/verify-code', ['email' => $owner->email, 'code' => $code]);

    $otherUser = User::factory()->create();

    $response = $this->post('/reset-password', [
        'email' => $otherUser->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Hash::check('new-password', $otherUser->fresh()->password))->toBeFalse();
});
