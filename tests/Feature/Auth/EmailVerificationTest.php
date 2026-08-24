<?php

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('registering sends a 6-digit verification code by mail', function () {
    Mail::fake();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    Mail::assertSent(VerificationCodeMail::class, function ($mail) use ($user) {
        return $mail->user->is($user) && preg_match('/^\d{6}$/', $mail->code) === 1;
    });

    expect($user->verification_code)->not->toBeNull();
    expect($user->verification_code_expires_at)->not->toBeNull();
});

test('email can be verified with a valid code', function () {
    Event::fake();
    Mail::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $code = null;
    Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $response = $this->actingAs($user)->post('/verify-email', ['code' => $code]);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->verification_code)->toBeNull();
    $response->assertRedirect('/');
});

test('email is not verified with an incorrect code', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $response = $this->actingAs($user)->post('/verify-email', ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a code cannot be used to verify a different account', function () {
    Mail::fake();

    $owner = User::factory()->unverified()->create();
    $owner->sendEmailVerificationNotification();

    $code = null;
    Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $otherUser = User::factory()->unverified()->create();

    $response = $this->actingAs($otherUser)->post('/verify-email', ['code' => $code]);

    $response->assertSessionHasErrors('code');
    expect($otherUser->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an expired code cannot verify the account', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $code = null;
    Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $user->forceFill(['verification_code_expires_at' => now()->subMinute()])->save();

    $response = $this->actingAs($user)->post('/verify-email', ['code' => $code]);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resending sends a fresh code and invalidates the previous one', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $firstCode = null;
    Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$firstCode) {
        $firstCode = $mail->code;

        return true;
    });

    $this->actingAs($user)->post('/email/verification-notification');

    Mail::assertSent(VerificationCodeMail::class, 2);

    $response = $this->actingAs($user)->post('/verify-email', ['code' => $firstCode]);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
