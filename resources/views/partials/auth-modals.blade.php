{{-- Login modal --}}
<div id="auth-modal-login" class="js-modal fixed inset-0 z-[100] {{ old('_modal') === 'login' ? 'flex' : 'hidden' }} items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
        <button type="button" class="js-modal-close absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full text-[#2B1113]/40 hover:bg-black/5 hover:text-[#2B1113]" aria-label="Cerrar">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
        </button>

        <div class="text-center mb-2">
            <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-10 w-auto mx-auto mb-4">
            <h2 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Inicia sesión</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Accede a tu cuenta para comprar tu boleto.</p>
        </div>

        <div class="mt-5">
            @include('auth.partials.login-form', ['idPrefix' => 'modal-login-'])
        </div>

        <p class="mt-6 text-center text-sm text-[#2B1113]/60">
            ¿No tienes cuenta?
            <button type="button" class="js-modal-switch font-semibold text-[#8C1D2B] hover:underline" data-auth-modal="register">Regístrate</button>
        </p>
    </div>
</div>

{{-- Register modal --}}
<div id="auth-modal-register" class="js-modal fixed inset-0 z-[100] {{ old('_modal') === 'register' ? 'flex' : 'hidden' }} items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
        <button type="button" class="js-modal-close absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full text-[#2B1113]/40 hover:bg-black/5 hover:text-[#2B1113]" aria-label="Cerrar">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
        </button>

        <div class="text-center mb-2">
            <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-10 w-auto mx-auto mb-4">
            <h2 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Crea tu cuenta</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Regístrate para comprar tus boletos con Merlo Transportes.</p>
        </div>

        <div class="mt-5">
            @include('auth.partials.register-form', ['idPrefix' => 'modal-register-'])
        </div>

        <p class="mt-6 text-center text-sm text-[#2B1113]/60">
            ¿Ya tienes cuenta?
            <button type="button" class="js-modal-switch font-semibold text-[#8C1D2B] hover:underline" data-auth-modal="login">Inicia sesión</button>
        </p>
    </div>
</div>
