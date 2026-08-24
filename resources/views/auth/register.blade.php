<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Crea tu cuenta</h1>
        <p class="mt-1 text-sm text-[#2B1113]/60">Regístrate para comprar tus boletos con Merlo Transportes.</p>
    </div>

    @include('auth.partials.register-form')

    <p class="mt-6 text-center text-sm text-[#2B1113]/60">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="font-semibold text-[#8C1D2B] hover:underline">Inicia sesión</a>
    </p>
</x-guest-layout>
