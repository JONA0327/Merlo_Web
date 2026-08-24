<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AdminUserController extends Controller
{
    /**
     * Show the form to create an internal (Administración / Paquetería)
     * user account. Client accounts are self-registered and are not
     * listed or managed from here.
     */
    public function create(): View
    {
        return view('admin.usuarios.create');
    }

    /**
     * Create an internal account with a random, unusable password — the
     * new user sets their own via the welcome email's reset-code link,
     * reusing the existing "forgot my password" flow end to end.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:'.User::ROLE_ADMINISTRACION.','.User::ROLE_PAQUETERIA],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);

        // The account already exists by this point — a flaky mail server
        // must never make it look like account creation failed.
        try {
            $user->sendInternalAccountWelcome();
            $message = 'Cuenta creada. Se envió un correo de bienvenida a '.$user->email.'.';
        } catch (Throwable $e) {
            Log::warning('Internal account welcome email failed to send', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $message = 'Cuenta creada, pero no pudimos enviarle el correo de bienvenida. Usa "Olvidé mi contraseña" para que la configure.';
        }

        return redirect()->route('admin.usuarios.create')->with('success', $message);
    }
}
