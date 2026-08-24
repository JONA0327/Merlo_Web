<x-guest-layout>
    <div class="text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-[#8C1D2B]/10 text-[#8C1D2B]">
            <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z"/><path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"/></svg>
        </span>
        <h1 class="mt-4 font-[Poppins] text-xl font-bold text-[#2B1113]">Verifica tu correo</h1>
        <p class="mt-2 text-sm text-[#2B1113]/60">
            Enviamos un código de 6 dígitos a <strong class="text-[#2B1113]">{{ auth()->user()->email }}</strong>.
            Ingrésalo para activar tu cuenta. El código vence en 15 minutos.
        </p>
    </div>

    @if (session('status') == 'verification-code-sent')
        <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            Te enviamos un nuevo código a tu correo.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.store') }}" class="mt-6">
        @csrf

        <x-input-label for="code" value="Código de verificación" />
        <x-text-input
            id="code"
            name="code"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            pattern="\d{6}"
            placeholder="000000"
            class="mt-1.5 block w-full text-center text-2xl tracking-[0.5em] font-[Poppins] font-bold"
            required
            autofocus
        />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />

        <x-primary-button class="mt-6 w-full justify-center py-3">
            Verificar cuenta
        </x-primary-button>
    </form>

    <div class="mt-6 flex items-center justify-between border-t border-black/5 pt-5">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="text-sm font-semibold text-[#8C1D2B] hover:underline">
                Reenviar código
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-[#2B1113]/50 hover:text-[#2B1113]">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>
