@php $idPrefix ??= ''; @endphp

<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="_modal" value="register">

    <div>
        <x-input-label :for="$idPrefix.'name'" value="Nombre completo" />
        <x-text-input :id="$idPrefix.'name'" class="block mt-1.5 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label :for="$idPrefix.'email'" value="Correo electrónico" />
        <x-text-input :id="$idPrefix.'email'" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label :for="$idPrefix.'phone'" value="Teléfono (opcional)" />
        <x-text-input :id="$idPrefix.'phone'" class="phone-mx-input block mt-1.5 w-full" type="tel" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="444 123 4567" name="phone" :value="old('phone')" />
        <p class="mt-1 text-xs text-[#2B1113]/50">Si lo registras, podemos enviarte tu boleto por WhatsApp. Si no, puedes agregarlo después en tu perfil.</p>
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <x-input-label :for="$idPrefix.'password'" value="Contraseña" />
        <x-text-input :id="$idPrefix.'password'" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label :for="$idPrefix.'password_confirmation'" value="Confirmar contraseña" />
        <x-text-input :id="$idPrefix.'password_confirmation'" class="block mt-1.5 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
    </div>

    <x-primary-button class="w-full justify-center py-3 mt-2">
        Crear cuenta
    </x-primary-button>
</form>
