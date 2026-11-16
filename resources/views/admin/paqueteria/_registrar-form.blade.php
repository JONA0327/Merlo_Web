{{--
    Formulario de registro de paquetes, compartido por la vista del personal
    (dentro del panel) y por la pública que abre el QR universal sin login.

    El costo nunca se captura aquí — este formulario solo recolecta; el precio
    se pone después, al entregar.

    $isStaff — true: lista de clientes conocidos.
               false: visitante sin sesión (no se le muestran datos de
               clientes) + campo trampa anti-bots.
    $clients — clientes conocidos (vacío para visitantes).
--}}
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
                // Reset so picking/shooting the exact same file again still fires @change.
                event.target.value = '';
                this.refresh();
            },
            remove(index) {
                files.splice(index, 1);
                this.refresh();
            },
            refresh() {
                this.previews.forEach((url) => URL.revokeObjectURL(url));
                this.previews = files.map((file) => URL.createObjectURL(file));
                // x-ref="photos" is the only input that gets submitted: keep it
                // equal to everything picked so far, from either source input.
                const transfer = new DataTransfer();
                files.forEach((file) => transfer.items.add(file));
                this.$refs.photos.files = transfer.files;
            },
        };
    }
</script>

<div
    x-data="photoPicker()"
    class="{{ $isStaff ? 'max-w-xl rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm' : '' }}"
>
    <form method="POST" action="{{ route('admin.paqueteria.registrar.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        @unless ($isStaff)
            {{-- Campo trampa: las personas no lo ven; un bot que llena todo lo rellena. --}}
            <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                <label>No llenar este campo <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
        @endunless

        <div>
            <x-input-label for="client_name" value="{{ $isStaff ? 'Nombre del cliente' : 'Tu nombre' }}" />
            <x-text-input id="client_name" name="client_name" type="text" required :list="$isStaff ? 'known-clients' : null" :autocomplete="$isStaff ? 'off' : 'name'" class="block mt-1.5 w-full" value="{{ old('client_name') }}" />
            @if ($isStaff)
                <datalist id="known-clients">
                    @foreach ($clients as $c)
                        <option value="{{ $c->name }}" data-phone="{{ $c->phone_formatted }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-[#2B1113]/50">Si el cliente ya ha enviado antes, elígelo de la lista y se carga su teléfono.</p>
            @endif
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
        <p class="-mt-3 text-xs text-[#2B1113]/50">Ahí le llegará el aviso con el/los folio(s).</p>

        <div>
            <x-input-label value="Fotos de evidencia (mínimo 1)" />
            <p class="mt-1 text-xs text-[#2B1113]/50">Sube una o varias fotos (hasta 10) que cubran todo el registro (ej. la pila completa, cada paquete, la etiqueta) — ayuda a evitar extravíos.</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="(url, i) in previews" :key="url">
                    <div class="relative h-24 w-24 overflow-hidden rounded-xl ring-1 ring-black/10">
                        <img :src="url" alt="Foto de evidencia" class="h-full w-full object-cover">
                        <button type="button" @click="remove(i)" aria-label="Quitar foto" class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#2B1113]/80 text-xs font-bold text-white hover:bg-[#8C1D2B]">&times;</button>
                    </div>
                </template>
                <label x-show="previews.length < max" class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-black/15 bg-[#FFFBF6] text-[#2B1113]/40 hover:border-[#8C1D2B]/40 transition-colors">
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 5h2l1.5-2h5L14 5h2a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2zm6 2.5a3.5 3.5 0 100 7 3.5 3.5 0 000-7z"/></svg>
                    <span class="text-[10px] font-bold">Tomar foto</span>
                    <input type="file" name="photos[]" accept="image/*" capture="environment" multiple required x-ref="photos" class="hidden" @change="add($event)">
                </label>
            </div>
            <p class="mt-1 text-xs text-[#2B1113]/50">Puedes usar "Tomar foto" varias veces: se van sumando.</p>
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

@if ($isStaff)
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
@endif
