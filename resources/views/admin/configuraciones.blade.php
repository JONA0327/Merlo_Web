<x-admin-layout active="configuraciones" title="Configuraciones">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Configuraciones</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Estos datos se usan en el sitio público: el formulario de cotización por WhatsApp y los íconos de redes sociales del pie de página.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-lg rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.configuraciones.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="whatsapp_number" value="Número de WhatsApp" />
                <x-text-input id="whatsapp_number" name="whatsapp_number" type="text" class="block mt-1.5 w-full" placeholder="5215512345678" value="{{ old('whatsapp_number', $setting->whatsapp_number) }}" />
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Con código de país, solo números (ej. 52 para México + el número a 10 dígitos). Aquí llegan las cotizaciones del sitio.</p>
                <x-input-error :messages="$errors->get('whatsapp_number')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="facebook_url" value="Link de Facebook" />
                <x-text-input id="facebook_url" name="facebook_url" type="url" class="block mt-1.5 w-full" placeholder="https://facebook.com/merlotransportes" value="{{ old('facebook_url', $setting->facebook_url) }}" />
                <x-input-error :messages="$errors->get('facebook_url')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="instagram_url" value="Link de Instagram" />
                <x-text-input id="instagram_url" name="instagram_url" type="url" class="block mt-1.5 w-full" placeholder="https://instagram.com/merlotransportes" value="{{ old('instagram_url', $setting->instagram_url) }}" />
                <x-input-error :messages="$errors->get('instagram_url')" class="mt-2" />
            </div>

            <div class="flex justify-end pt-2">
                <x-primary-button type="submit">
                    Guardar
                </x-primary-button>
            </div>
        </form>
    </div>
</x-admin-layout>
