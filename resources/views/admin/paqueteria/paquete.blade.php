<x-admin-layout active="paqueteria" title="Paquete {{ $package->tracking_code }}">
    @php
        $statusMeta = [
            'sin_asignar' => ['label' => 'Sin asignar', 'class' => 'bg-black/5 text-[#2B1113]/60'],
            'recolectado' => ['label' => 'Recolectado', 'class' => 'bg-[#F5B301]/15 text-[#8C6B00]'],
            'en_recorrido' => ['label' => 'En recorrido', 'class' => 'bg-blue-100 text-blue-700'],
            'entregado' => ['label' => 'Entregado', 'class' => 'bg-emerald-100 text-emerald-700'],
            'no_entregado' => ['label' => 'No entregado', 'class' => 'bg-red-100 text-red-700'],
        ];
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">{{ $package->tracking_code }}</h2>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusMeta[$package->status]['class'] }}">
                {{ $statusMeta[$package->status]['label'] }}
            </span>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($package->status === \App\Models\Package::STATUS_SIN_ASIGNAR)
        @vite(['resources/js/package-scanner.js'])

        <div
            x-data="{
                codes: [],
                newCode: '',
                extraPhotoName: null,
                extraPhotoError: null,
                mainPhotoPreview: null,
                scanning: false,
                scanError: null,
                scanner: null,
                addCode() {
                    // Read straight from the input rather than trusting the
                    // x-model-bound newCode: on some mobile browsers, pasting
                    // via the OS context menu updates the field's value
                    // without firing the 'input' event Alpine listens for,
                    // so newCode can be stale (still empty) at click time.
                    const raw = this.$refs.extraCodeInput.value;
                    const value = this.extractCode(raw.trim()).toUpperCase();
                    if (! value || value === '{{ $package->tracking_code }}' || this.codes.some((c) => c.code === value)) {
                        this.newCode = '';
                        this.$refs.extraCodeInput.value = '';
                        return;
                    }
                    const photoFile = this.$refs.extraPhotoInput.files[0];
                    if (! photoFile) {
                        this.extraPhotoError = 'Toma o sube una foto de este paquete antes de agregarlo.';
                        return;
                    }
                    this.extraPhotoError = null;
                    this.codes.push({ code: value, photo: photoFile });
                    this.newCode = '';
                    this.$refs.extraCodeInput.value = '';
                    this.$refs.extraPhotoInput.value = '';
                    this.extraPhotoName = null;
                    this.syncExtraPhotos();
                },
                removeCode(code) {
                    this.codes = this.codes.filter((c) => c.code !== code);
                    this.syncExtraPhotos();
                },
                syncExtraPhotos() {
                    const dt = new DataTransfer();
                    this.codes.forEach((c) => dt.items.add(c.photo));
                    this.$refs.extraPhotosHidden.files = dt.files;
                },
                extractCode(scanned) {
                    try {
                        const url = new URL(scanned);
                        const segments = url.pathname.split('/').filter(Boolean);
                        return segments[segments.length - 1] ?? scanned;
                    } catch {
                        return scanned;
                    }
                },
                async startScan() {
                    this.scanError = null;
                    if (! window.QrScanner) {
                        this.scanError = 'El escáner no cargó correctamente. Recarga la página.';
                        return;
                    }
                    this.scanning = true;
                    await this.$nextTick();
                    this.scanner = new window.QrScanner(
                        this.$refs.scannerVideo,
                        (result) => {
                            const code = this.extractCode(result.data);
                            this.newCode = code;
                            this.$refs.extraCodeInput.value = code;
                            this.stopScan();
                        },
                        { returnDetailedScanResult: true, highlightScanRegion: true, highlightCodeOutline: true },
                    );
                    try {
                        await this.scanner.start();
                    } catch (error) {
                        this.scanError = 'No pudimos acceder a la cámara. Revisa los permisos del navegador.';
                        this.stopScan();
                    }
                },
                stopScan() {
                    this.scanner?.stop();
                    this.scanner?.destroy();
                    this.scanner = null;
                    this.scanning = false;
                },
            }"
            class="max-w-xl rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm"
        >
            <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Registrar paquete</h3>
            <p class="mt-1 text-sm text-[#2B1113]/60">Captura los datos del cliente para asignar este código.</p>

            <form method="POST" action="{{ route('admin.paqueteria.paquetes.assign', $package) }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="client_name" value="Nombre del cliente" />
                    <x-text-input id="client_name" name="client_name" type="text" class="block mt-1.5 w-full" value="{{ old('client_name') }}" />
                </div>

                <div>
                    <x-input-label for="client_email" value="Correo del cliente" />
                    <x-text-input id="client_email" name="client_email" type="email" class="block mt-1.5 w-full" value="{{ old('client_email') }}" />
                    <p class="mt-1.5 text-xs text-[#2B1113]/50">Ahí le llegará su código de rastreo.</p>
                </div>

                <div>
                    <x-input-label for="price" value="Precio del envío" />
                    <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="block mt-1.5 w-full" value="{{ old('price') }}" />
                    <p class="mt-1.5 text-xs text-[#2B1113]/50">Si agregas más paquetes abajo, este precio es el total del envío.</p>
                </div>

                <div>
                    <x-input-label value="Foto de evidencia (obligatoria)" />
                    <p class="mt-1 text-xs text-[#2B1113]/50">Tómale una foto al paquete antes de guardarlo — ayuda a evitar extravíos.</p>
                    <label class="mt-2 flex h-24 w-24 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-black/15 bg-[#FFFBF6] hover:border-[#8C1D2B]/40 transition-colors">
                        <img x-show="mainPhotoPreview" x-cloak :src="mainPhotoPreview" class="h-full w-full object-cover">
                        <svg x-show="! mainPhotoPreview" class="h-7 w-7 text-[#2B1113]/25" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 2H8.828a2 2 0 00-1.414.586L6.293 3.707A1 1 0 015.586 4H4zm6 3a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>
                        <input
                            type="file"
                            name="photo"
                            accept="image/*"
                            capture="environment"
                            required
                            class="hidden"
                            @change="mainPhotoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                        >
                    </label>
                    <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                </div>

                <div class="border-t border-black/5 pt-5">
                    <x-input-label value="¿Es más de un paquete del mismo cliente?" />
                    <p class="mt-1 text-xs text-[#2B1113]/50">Escanea o escribe el código de cada paquete adicional, y toma su foto — se cobrarán como un solo envío con el precio de arriba.</p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <input
                            type="text"
                            x-ref="extraCodeInput"
                            x-model="newCode"
                            @keydown.enter.prevent="addCode()"
                            placeholder="Código del otro paquete"
                            class="flex-1 rounded-xl border-black/10 uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case text-sm focus:border-[#8C1D2B] focus:ring-[#8C1D2B]"
                        >
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-black/10 px-3 py-2 text-xs font-bold text-[#2B1113]/70 hover:bg-black/5 transition-colors">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 2H8.828a2 2 0 00-1.414.586L6.293 3.707A1 1 0 015.586 4H4zm6 3a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>
                            <span x-text="extraPhotoName ?? 'Foto'" class="max-w-[7rem] truncate"></span>
                            <input type="file" x-ref="extraPhotoInput" accept="image/*" capture="environment" class="hidden" @change="extraPhotoName = $event.target.files[0]?.name ?? null">
                        </label>
                        <button type="button" @click="scanning ? stopScan() : startScan()" class="inline-flex items-center gap-1.5 rounded-xl bg-[#8C1D2B]/10 px-4 py-2 text-sm font-bold text-[#8C1D2B] hover:bg-[#8C1D2B]/15 transition-colors">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M4 2a1 1 0 000 2h1v1a1 1 0 002 0V4h1a1 1 0 100-2H4zM4 16a1 1 0 100 2h3a1 1 0 100-2H5v-1a1 1 0 10-2 0v1zM16 2a1 1 0 010 2h-1v1a1 1 0 11-2 0V4h-1a1 1 0 110-2h4zM16 18a1 1 0 000-2h-1v-1a1 1 0 10-2 0v1h-1a1 1 0 100 2h4zM7 8a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H8a1 1 0 01-1-1V8z"/></svg>
                            <span x-text="scanning ? 'Cancelar' : 'Escanear'"></span>
                        </button>
                        <button type="button" @click="addCode()" class="rounded-xl bg-[#2B1113] px-4 py-2 text-sm font-bold text-white hover:bg-[#2B1113]/90 transition-colors">
                            Agregar
                        </button>
                    </div>

                    <div x-show="scanning" x-cloak class="mt-3 overflow-hidden rounded-xl bg-black">
                        <video x-ref="scannerVideo" class="aspect-square w-full max-w-xs mx-auto"></video>
                    </div>
                    <p x-show="scanError" x-cloak x-text="scanError" class="mt-2 text-xs font-semibold text-red-600"></p>
                    <p x-show="extraPhotoError" x-cloak x-text="extraPhotoError" class="mt-2 text-xs font-semibold text-red-600"></p>

                    <div class="mt-3 flex flex-wrap gap-2" x-show="codes.length > 0">
                        <template x-for="entry in codes" :key="entry.code">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#8C1D2B]/10 px-3 py-1 text-xs font-bold text-[#8C1D2B]">
                                <span x-text="entry.code"></span>
                                <svg class="h-3.5 w-3.5 text-[#8C1D2B]/60" viewBox="0 0 20 20" fill="currentColor" title="Foto adjunta"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                <button type="button" @click="removeCode(entry.code)" class="text-[#8C1D2B]/60 hover:text-[#8C1D2B]">&times;</button>
                                <input type="hidden" name="codes[]" :value="entry.code">
                            </span>
                        </template>
                    </div>
                    <input type="file" name="photos[]" multiple x-ref="extraPhotosHidden" class="hidden">
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-primary-button type="submit">
                        Registrar
                    </x-primary-button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.paqueteria.paquetes.destroy', $package) }}" class="mt-4">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700" onclick="return confirm('¿Eliminar el código {{ $package->tracking_code }}? La etiqueta impresa dejará de funcionar.')">
                    Eliminar este código
                </button>
            </form>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Datos del envío</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-[#2B1113]/50">Cliente</dt>
                        <dd class="font-semibold text-[#2B1113]">{{ $package->displayClientName() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#2B1113]/50">Correo</dt>
                        <dd class="font-semibold text-[#2B1113]">{{ $package->displayClientEmail() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#2B1113]/50">Precio</dt>
                        <dd class="font-semibold text-[#2B1113]">${{ number_format((float) $package->displayPrice(), 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#2B1113]/50">Recolectado</dt>
                        <dd class="font-semibold text-[#2B1113]">{{ $package->collected_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    @if ($package->delivered_at)
                        <div class="flex justify-between">
                            <dt class="text-[#2B1113]/50">Entregado</dt>
                            <dd class="font-semibold text-[#2B1113]">{{ $package->delivered_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($package->isGrouped())
                    <div class="mt-5 rounded-xl bg-[#FFFBF6] px-4 py-3">
                        <p class="text-xs text-[#2B1113]/60">
                            Parte del envío <strong>{{ $package->group->tracking_code }}</strong> junto con {{ $package->group->packages->count() - 1 }} paquete(s) más.
                        </p>
                    </div>
                @endif

                @if ($package->photo_path)
                    <div class="mt-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Foto de evidencia</p>
                        <a href="{{ route('admin.paqueteria.paquetes.photo', $package) }}" target="_blank" class="mt-2 block w-40 overflow-hidden rounded-xl ring-1 ring-black/5">
                            <img src="{{ route('admin.paqueteria.paquetes.photo', $package) }}" alt="Evidencia de {{ $package->tracking_code }}" class="h-40 w-40 object-cover">
                        </a>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Actualizar estado</h3>
                <p class="mt-1 text-sm text-[#2B1113]/60">Cambia el estado conforme el paquete avanza.</p>

                <form method="POST" action="{{ route('admin.paqueteria.paquetes.update-status', $package) }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="status" value="Nuevo estado" />
                        <select id="status" name="status" class="mt-1.5 block w-full rounded-xl border-black/10 focus:border-[#8C1D2B] focus:ring-[#8C1D2B]">
                            @foreach (['recolectado', 'en_recorrido', 'entregado', 'no_entregado'] as $option)
                                <option value="{{ $option }}" @selected($package->status === $option)>{{ $statusMeta[$option]['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end pt-2">
                        <x-primary-button type="submit">
                            Guardar
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-admin-layout>
