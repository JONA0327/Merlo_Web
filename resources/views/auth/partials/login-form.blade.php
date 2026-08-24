@php $idPrefix ??= ''; @endphp

<x-auth-session-status class="mb-4" :status="session('status')" />

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="_modal" value="login">

    <div>
        <x-input-label :for="$idPrefix.'email'" value="Correo electrónico" />
        <x-text-input :id="$idPrefix.'email'" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label :for="$idPrefix.'password'" value="Contraseña" />
        <x-text-input :id="$idPrefix.'password'" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="current-password" />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <label class="inline-flex items-center">
        <input type="checkbox" class="rounded border-gray-300 text-[#8C1D2B] shadow-sm focus:ring-[#8C1D2B]" name="remember">
        <span class="ms-2 text-sm text-[#2B1113]/70">Recuérdame</span>
    </label>

    <div class="flex items-center justify-between gap-4 pt-2">
        @if (Route::has('password.request'))
            <a class="text-sm text-[#2B1113]/60 hover:text-[#8C1D2B] underline" href="{{ route('password.request') }}">
                ¿Olvidaste tu contraseña?
            </a>
        @endif

        <x-primary-button>
            Iniciar sesión
        </x-primary-button>
    </div>
</form>
