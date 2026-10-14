<x-admin-layout active="paqueteria" title="Registrar paquete · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Registrar paquete</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Captura los datos del cliente — se le asigna un folio por cada paquete (contiguo, ej. del F-000101 al F-000105) y se le avisa por WhatsApp.</p>
    </div>

    <div
        x-data="{ photoPreview: null }"
        class="max-w-xl rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm"
    >
        <form method="POST" action="{{ route('admin.paqueteria.registrar.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="client_name" value="Nombre del cliente" />
                <x-text-input id="client_name" name="client_name" type="text" required class="block mt-1.5 w-full" value="{{ old('client_name') }}" />
                <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="qty" value="Cantidad de paquetes" />
                    <x-text-input id="qty" name="qty" type="number" min="1" max="50" required class="block mt-1.5 w-full" value="{{ old('qty', 1) }}" />
                    <x-input-error :messages="$errors->get('qty')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="phone" value="Teléfono (WhatsApp)" />
                    <x-text-input id="phone" name="phone" type="tel" inputmode="numeric" required maxlength="12" placeholder="444 123 4567" class="phone-mx-input block mt-1.5 w-full" value="{{ old('phone') }}" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
            </div>
            <p class="-mt-3 text-xs text-[#2B1113]/50">Ahí le llegará el aviso con el/los folio(s) y el total.</p>

            <div>
                <x-input-label for="price" value="Total a pagar (opcional)" />
                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="block mt-1.5 w-full" value="{{ old('price') }}" />
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Si se deja vacío, no se incluye un total en el aviso de WhatsApp.</p>
                <x-input-error :messages="$errors->get('price')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Foto de evidencia (obligatoria)" />
                <p class="mt-1 text-xs text-[#2B1113]/50">Una sola foto que cubra todo el registro (ej. la pila completa) — ayuda a evitar extravíos.</p>
                <label class="mt-2 flex h-24 w-24 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-black/15 bg-[#FFFBF6] hover:border-[#8C1D2B]/40 transition-colors">
                    <img x-show="photoPreview" x-cloak :src="photoPreview" class="h-full w-full object-cover">
                    <svg x-show="! photoPreview" class="h-7 w-7 text-[#2B1113]/25" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 2H8.828a2 2 0 00-1.414.586L6.293 3.707A1 1 0 015.586 4H4zm6 3a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>
                    <input
                        type="file"
                        name="photo"
                        accept="image/*"
                        capture="environment"
                        required
                        class="hidden"
                        @change="photoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                    >
                </label>
                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <x-primary-button type="submit">
                    Registrar
                </x-primary-button>
            </div>
        </form>
    </div>
</x-admin-layout>
