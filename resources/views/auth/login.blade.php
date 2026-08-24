<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Inicia sesión</h1>
        <p class="mt-1 text-sm text-[#2B1113]/60">Accede a tu cuenta de Merlo Transportes.</p>
    </div>

    @include('auth.partials.login-form')

    <p class="mt-6 text-center text-sm text-[#2B1113]/60">
        ¿No tienes cuenta?
        <a href="{{ route('register') }}" class="font-semibold text-[#8C1D2B] hover:underline">Regístrate</a>
    </p>
</x-guest-layout>
