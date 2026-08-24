<x-admin-layout active="usuarios" title="Usuarios">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Crear usuario</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">
            Este panel es solo para crear cuentas internas de <strong>Administración</strong> o <strong>Paquetería</strong>.
            Los clientes se registran ellos mismos desde el sitio, así que aquí no se muestra su listado.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-2xl rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.usuarios.store') }}" class="space-y-6">
            @csrf

            <div>
                <x-input-label for="name" value="Nombre completo" />
                <x-text-input id="name" name="name" type="text" class="block mt-1.5 w-full" placeholder="Ej. María Torres" :value="old('name')" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Correo electrónico" />
                <x-text-input id="email" name="email" type="email" class="block mt-1.5 w-full" placeholder="nombre@merlotransportes.com" :value="old('email')" />
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Se usará para iniciar sesión y para enviarle las instrucciones de acceso.</p>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <fieldset>
                <legend class="block font-medium text-sm text-gray-700 mb-2">Rol del usuario</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="relative flex cursor-pointer rounded-xl border-2 border-[#8C1D2B] bg-[#8C1D2B]/5 p-4">
                        <input type="radio" name="role" value="administracion" class="mt-1 h-4 w-4 shrink-0 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ old('role', 'administracion') === 'administracion' ? 'checked' : '' }}>
                        <span class="ml-3">
                            <span class="block text-sm font-bold text-[#2B1113]">Administración</span>
                            <span class="block text-xs text-[#2B1113]/60 mt-0.5">Acceso a viajes, asientos y ventas.</span>
                        </span>
                    </label>

                    <label class="relative flex cursor-pointer rounded-xl border-2 border-black/10 p-4 hover:border-[#8C1D2B]/40 transition-colors">
                        <input type="radio" name="role" value="paqueteria" class="mt-1 h-4 w-4 shrink-0 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ old('role') === 'paqueteria' ? 'checked' : '' }}>
                        <span class="ml-3">
                            <span class="block text-sm font-bold text-[#2B1113]">Paquetería</span>
                            <span class="block text-xs text-[#2B1113]/60 mt-0.5">Acceso solo a la sección de paquetería.</span>
                        </span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </fieldset>

            <div class="flex items-center gap-2 rounded-xl bg-[#FFFBF6] px-4 py-3">
                <svg class="h-4 w-4 shrink-0 text-[#8C1D2B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <p class="text-xs text-[#2B1113]/60">Le enviaremos un correo de bienvenida con instrucciones para establecer su contraseña.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">
                    Cancelar
                </a>
                <x-primary-button type="submit">
                    Crear usuario
                </x-primary-button>
            </div>
        </form>
    </div>
</x-admin-layout>
