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
     * Every registered account — self-registered clients as well as
     * internal Administración/Paquetería accounts created below. Client
     * signups never went through any admin action, so this listing is
     * the only place a superadmin can see who has registered.
     */
    public function index(Request $request): View
    {
        $query = User::query()->withCount('seatReservations');

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.usuarios.index', [
            'users' => $users,
            'filters' => [
                'role' => $role,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Show the form to create an internal (Administración / Paquetería)
     * user account. Client accounts are self-registered and appear
     * automatically in the listing above — they're never created here.
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

        return redirect()->route('admin.usuarios.index')->with('success', $message);
    }
}
