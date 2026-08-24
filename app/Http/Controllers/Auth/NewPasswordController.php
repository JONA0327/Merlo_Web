<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Session key that proves a given email's code was already checked,
     * so the password step doesn't have to ask for the code again.
     */
    private const SESSION_KEY = 'password_reset_verified';

    /**
     * Display the reset view: the code step, or — once that email's code
     * has been verified in this session — the new password step.
     */
    public function create(Request $request): View
    {
        $email = $request->query('email');

        return view('auth.reset-password', [
            'email' => $email,
            'verified' => $this->isVerified($request, $email),
        ]);
    }

    /**
     * Validate the 6-digit code against the hash stored on that email's
     * own account. It isn't consumed here — only flagged as verified for
     * this session — so the user can move on to choosing a new password.
     *
     * @throws ValidationException
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->passwordResetCodeIsValid($request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => 'Este código no es válido o ya expiró. Solicita uno nuevo.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'email' => $user->email,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return redirect()->route('password.reset', ['email' => $user->email]);
    }

    /**
     * Set the new password. Only reachable once verifyCode() has flagged
     * this email as verified for the current session.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $this->isVerified($request, $request->email)) {
            throw ValidationException::withMessages([
                'email' => 'Tu verificación expiró. Solicita un nuevo código.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        $user->clearPasswordResetCode();
        $request->session()->forget(self::SESSION_KEY);

        event(new PasswordReset($user));

        Auth::login($user);

        return redirect('/')->with('status', 'password-reset');
    }

    private function isVerified(Request $request, ?string $email): bool
    {
        if (! $email) {
            return false;
        }

        $verified = $request->session()->get(self::SESSION_KEY);

        return $verified
            && $verified['email'] === $email
            && $verified['expires_at'] > now()->timestamp;
    }
}
