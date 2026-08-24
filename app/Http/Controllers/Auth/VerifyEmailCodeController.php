<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailCodeController extends Controller
{
    /**
     * Validate the 6-digit code entered by the user and, if it matches
     * the hash stored on THEIR account and hasn't expired, mark the
     * email as verified.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        if (! $user->verificationCodeIsValid($request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => 'Este código no es válido o ya expiró. Solicita uno nuevo.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user->clearVerificationCode();

        return redirect()->intended('/')->with('status', 'verified');
    }
}
