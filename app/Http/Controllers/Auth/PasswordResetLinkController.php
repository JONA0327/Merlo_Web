<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a 6-digit password reset code to the given email, if an
     * account exists for it. The response is identical either way so
     * the form can't be used to discover which emails are registered.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        $user?->sendPasswordResetCode();

        return redirect()->route('password.reset', ['email' => $request->email])
            ->with('status', 'reset-code-sent');
    }
}
