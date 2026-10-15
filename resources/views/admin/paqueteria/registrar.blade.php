<x-admin-layout active="paqueteria" title="Registrar paquete · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Registrar paquete</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Captura los datos del cliente — se le asigna un folio por cada paquete (contiguo, ej. del F-000101 al F-000105) y se le avisa por WhatsApp.</p>
    </div>

    <script>
        // The File objects live in a closure (not in Alpine's reactive state):
        // a reactive Proxy around a File breaks DataTransfer.items.add().
        function photoPicker() {
            let files = [];
            return {
                previews: [],
                max: 10,
                add(event) {
                    files = [...files, ...Array.from(event.target.files)].slice(0, this.max);
                    this.refresh(event.target);
                },
                remove(index) {
                    files.splice(index, 1);
                    this.refresh(this.$refs.photos);
                },
                refresh(input) {
                    this.previews.forEach((url) => URL.revokeObjectURL(url));
                    this.previews = files.map((file) => URL.createObjectURL(file));
                    // The input is what gets submitted: keep it equal to everything picked so far.
                    const transfer = new DataTransfer();
                    files.forEach((file) => transfer.items.add(file));
                    input.files = transfer.files;
                },
            };
        }
    </script>

    <div
        x-data="photoPicker()"
        class="max-w-xl rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm"
    >
        <form method="POST" action="{{ route('admin.paqueteria.registrar.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="client_name" value="Nombre del cliente" />
                <x-text-input id="client_name" name="client_name" type="text" required list="known-clients" autocomplete="off" class="block mt-1.5 w-full" value="{{ old('client_name') }}" />
                <datalist id="known-clients">
                    @foreach ($clients as $c)
                        <option value="{{ $c->name }}" data-phone="{{ $c->phone_formatted }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-[#2B1113]/50">Si el cliente ya ha enviado antes, elígelo de la lista y se carga su teléfono.</p>
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
                <x-input-label value="Fotos de evidencia (mínimo 1)" />
                <p class="mt-1 text-xs text-[#2B1113]/50">Sube una o varias fotos (hasta 10) que cubran todo el registro (ej. la pila completa, cada paquete, la etiqueta) — ayuda a evitar extravíos. Puedes tomar o elegir fotos varias veces: se van sumando.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <template x-for="(url, i) in previews" :key="url">
                        <div class="relative h-24 w-24 overflow-hidden rounded-xl ring-1 ring-black/10">
                            <img :src="url" alt="Foto de evidencia" class="h-full w-full object-cover">
                            <button type="button" @click="remove(i)" aria-label="Quitar foto" class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#2B1113]/80 text-xs font-bold text-white hover:bg-[#8C1D2B]">&times;</button>
                        </div>
                    </template>
                    <label x-show="previews.length < max" class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-black/15 bg-[#FFFBF6] text-[#2B1113]/40 hover:border-[#8C1D2B]/40 transition-colors">
                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 2H8.828a2 2 0 00-1.414.586L6.293 3.707A1 1 0 015.586 4H4zm6 3a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>
                        <span class="text-[10px] font-bold">Agregar</span>
                        <input type="file" name="photos[]" accept="image/*" multiple required x-ref="photos" class="hidden" @change="add($event)">
                    </label>
                </div>
                <p class="mt-1 text-xs text-[#2B1113]/50"><span x-text="previews.length"></span> / <span x-text="max"></span> fotos</p>
                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                <x-input-error :messages="$errors->get('photos')" class="mt-2" />
                @foreach ($errors->get('photos.*') as $messages)
                    <x-input-error :messages="$messages" class="mt-1" />
                @endforeach
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <x-primary-button type="submit">
                    Registrar
                </x-primary-button>
            </div>
        </form>
    </div>
<script>
    document.getElementById('client_name').addEventListener('input', function () {
        const match = [...document.querySelectorAll('#known-clients option')].find(o => o.value === this.value);
        if (match) {
            const phone = document.getElementById('phone');
            phone.value = match.dataset.phone;
            phone.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
</script>
</x-admin-layout>
