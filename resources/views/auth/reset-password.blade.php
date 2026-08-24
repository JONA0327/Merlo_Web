<x-guest-layout>
    @if (session('status') === 'reset-code-sent')
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            Si ese correo está registrado, te enviamos un código de verificación.
        </div>
    @endif

    @if (! $verified)
        {{-- Paso 1: código --}}
        <div class="mb-6 text-center">
            <h1 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Ingresa tu código</h1>
            <p class="mt-2 text-sm text-[#2B1113]/60">
                @if ($email)
                    Escribe el código de 6 dígitos que enviamos a <strong class="text-[#2B1113]">{{ $email }}</strong>.
                @else
                    Escribe el código de 6 dígitos que enviamos a tu correo.
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('password.verify') }}" class="space-y-4">
            @csrf

            @if ($email)
                <input type="hidden" name="email" value="{{ $email }}">
            @else
                <div>
                    <x-input-label for="email" value="Correo electrónico" />
                    <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            @endif

            <div>
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
                    :autofocus="(bool) $email"
                />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <x-primary-button class="w-full justify-center py-3">
                Verificar código
            </x-primary-button>
        </form>

        <p class="mt-6 text-center text-sm text-[#2B1113]/60">
            ¿No recibiste el código?
            <a href="{{ route('password.request') }}" class="font-semibold text-[#8C1D2B] hover:underline">Solicitar uno nuevo</a>
        </p>
    @else
        {{-- Paso 2: nueva contraseña (solo tras verificar el código) --}}
        <div class="mb-6 text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-700">
                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
            </span>
            <h1 class="mt-4 font-[Poppins] text-xl font-bold text-[#2B1113]">Elige tu nueva contraseña</h1>
            <p class="mt-2 text-sm text-[#2B1113]/60">Código verificado para <strong class="text-[#2B1113]">{{ $email }}</strong>.</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <x-input-label for="password" value="Nueva contraseña" />
                <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="new-password" autofocus />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                <x-text-input id="password_confirmation" class="block mt-1.5 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <x-input-error :messages="$errors->get('email')" class="mt-2" />

            <x-primary-button class="w-full justify-center py-3">
                Restablecer contraseña
            </x-primary-button>
        </form>
    @endif
</x-guest-layout>
