<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-[Poppins] text-xl font-bold text-[#2B1113]">¿Olvidaste tu contraseña?</h1>
        <p class="mt-2 text-sm text-[#2B1113]/60">
            Escribe tu correo y te enviaremos un código de 6 dígitos para restablecerla.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center py-3">
            Enviar código
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-[#2B1113]/60">
        <a href="{{ route('login') }}" class="font-semibold text-[#8C1D2B] hover:underline">Volver a iniciar sesión</a>
    </p>
</x-guest-layout>
