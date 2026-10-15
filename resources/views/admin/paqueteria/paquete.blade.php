<x-admin-layout active="paqueteria" title="Paquete {{ $package->folio_label ?? $package->tracking_code }}">
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
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">{{ $package->folio_label ?? $package->tracking_code }}</h2>
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
        <div class="max-w-xl rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Folio reservado, aún sin registrar</h3>
            <p class="mt-1 text-sm text-[#2B1113]/60">Este folio ya fue impreso en una guía de folios pero todavía no se usó. Para registrarlo, escanea el <a href="{{ route('admin.paqueteria.qr-universal') }}" class="font-semibold text-[#8C1D2B] hover:underline">QR universal</a> y captura los datos del cliente ahí — ese formulario toma automáticamente los folios ya impresos antes de crear nuevos.</p>

            <form method="POST" action="{{ route('admin.paqueteria.paquetes.destroy', $package) }}" class="mt-6">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700" onclick="return confirm('¿Eliminar el folio {{ $package->folio_label }}? La etiqueta impresa dejará de funcionar.')">
                    Eliminar este folio
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
                        <dt class="text-[#2B1113]/50">Teléfono</dt>
                        <dd class="font-semibold text-[#2B1113]">{{ $package->displayPhone() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#2B1113]/50">Total</dt>
                        <dd class="font-semibold text-[#2B1113]">{{ $package->displayPrice() !== null ? '$'.number_format((float) $package->displayPrice(), 2) : 'Sin especificar' }}</dd>
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
                            Parte de un envío junto con {{ $package->group->packages->count() - 1 }} paquete(s) más — folios:
                            {{ $package->group->packages->sortBy('folio_number')->pluck('folio_label')->implode(', ') }}.
                        </p>
                    </div>
                @endif

                @if ($package->displayPhotoPath())
                    @php $extraPhotos = $package->evidencePhotos(); @endphp
                    <div class="mt-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">{{ $extraPhotos->isEmpty() ? 'Foto de evidencia' : 'Fotos de evidencia ('.($extraPhotos->count() + 1).')' }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a href="{{ route('admin.paqueteria.paquetes.photo', $package) }}" target="_blank" class="block w-40 overflow-hidden rounded-xl ring-1 ring-black/5">
                                <img src="{{ route('admin.paqueteria.paquetes.photo', $package) }}" alt="Evidencia de {{ $package->folio_label }}" class="h-40 w-40 object-cover">
                            </a>
                            @foreach ($extraPhotos as $extra)
                                <a href="{{ route('admin.paqueteria.paquetes.extra-photo', [$package, $extra]) }}" target="_blank" class="block w-40 overflow-hidden rounded-xl ring-1 ring-black/5">
                                    <img src="{{ route('admin.paqueteria.paquetes.extra-photo', [$package, $extra]) }}" alt="Evidencia {{ $loop->iteration + 1 }} de {{ $package->folio_label }}" class="h-40 w-40 object-cover">
                                </a>
                            @endforeach
                        </div>
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
                            @foreach (['recolectado', 'en_recorrido', 'no_entregado'] as $option)
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

        @php
            $members = $package->isGrouped() ? $package->group->packages->sortBy('folio_number') : collect([$package]);
            $undelivered = $members->where('status', '!=', \App\Models\Package::STATUS_ENTREGADO);
            $billed = $unit->billedAmount();
            $money = fn ($v) => '$'.number_format((float) $v, 2);
        @endphp
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Cobro</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-[#2B1113]/50">Costo total</dt><dd class="font-semibold text-[#2B1113]">{{ $billed !== null ? $money($billed) : 'Sin capturar' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/50">Pagado</dt><dd class="font-semibold text-emerald-700">{{ $money($unit->amount_paid) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/50">Pendiente</dt><dd class="font-semibold {{ $unit->pendingAmount() > 0 ? 'text-red-600' : 'text-[#2B1113]' }}">{{ $money($unit->pendingAmount()) }}</dd></div>
                </dl>
                @if ($unit->payments->isNotEmpty())
                    <p class="mt-5 text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Pagos</p>
                    <ul class="mt-2 space-y-1 text-sm text-[#2B1113]/70">
                        @foreach ($unit->payments as $pay)
                            <li class="flex justify-between"><span>{{ $pay->paid_at->format('d/m/Y H:i') }}{{ $pay->user ? ' · '.$pay->user->name : '' }}</span><span>{{ $money($pay->amount) }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($package->client)
                    <a href="{{ route('admin.paqueteria.clientes.show', $package->client) }}" class="mt-5 inline-block text-sm font-semibold text-[#8C1D2B] hover:underline">Ver historial del cliente &rarr;</a>
                @endif
            </div>

            <div class="rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Entrega</h3>
                @if ($undelivered->isEmpty())
                    <p class="mt-1 text-sm text-emerald-700 font-semibold">Todos los paquetes de este envío fueron entregados.</p>
                    @if ($package->delivery_photo_path)
                        <a href="{{ route('admin.paqueteria.paquetes.delivery-photo', $package) }}" target="_blank" class="mt-3 inline-block text-sm font-semibold text-[#8C1D2B] hover:underline">Ver evidencia de entrega</a>
                    @endif
                @else
                    <p class="mt-1 text-sm text-[#2B1113]/60">Marca los folios que se entregan; los demás quedan pendientes. Se avisa por WhatsApp al cliente.</p>
                    <form method="POST" action="{{ route('admin.paqueteria.paquetes.deliver', $package) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <div class="space-y-2">
                            @foreach ($members as $m)
                                <label class="flex items-center gap-3 text-sm {{ $m->status === 'entregado' ? 'text-[#2B1113]/40' : 'text-[#2B1113]' }}">
                                    @if ($m->status === 'entregado')
                                        <span class="w-4 text-emerald-600">&#10003;</span>
                                    @else
                                        <input type="checkbox" name="package_ids[]" value="{{ $m->id }}" checked class="rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]">
                                    @endif
                                    <span class="font-semibold">{{ $m->folio_label }}</span>
                                    <span class="text-xs">{{ $m->status === 'entregado' ? 'Entregado' : 'Pendiente' }}</span>
                                </label>
                            @endforeach
                        </div>

                        @unless ($billed > 0)
                            <div>
                                <x-input-label for="price" value="Costo del envío" />
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="block mt-1.5 w-full" value="{{ old('price') }}" />
                                <p class="mt-1 text-xs text-[#2B1113]/50">No se capturó al registrar; ponlo aquí.</p>
                            </div>
                        @endunless
                        <div>
                            <x-input-label for="amount_paid" value="Cobrado ahora" />
                            <x-text-input id="amount_paid" name="amount_paid" type="number" step="0.01" min="0" class="block mt-1.5 w-full" value="{{ old('amount_paid') }}" />
                            <p class="mt-1 text-xs text-[#2B1113]/50">Lo que falte para el costo total queda como pendiente del cliente.</p>
                        </div>
                        <div x-data="{ photoPreview: null }">
                            <x-input-label value="Evidencia de entrega (opcional)" />
                            <p class="mt-1 text-xs text-[#2B1113]/50">Toca para tomar una foto del paquete entregado.</p>
                            <label class="mt-2 flex h-24 w-24 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-black/15 bg-[#FFFBF6] hover:border-[#8C1D2B]/40 transition-colors">
                                <img x-show="photoPreview" x-cloak :src="photoPreview" class="h-full w-full object-cover">
                                <svg x-show="! photoPreview" class="h-7 w-7 text-[#2B1113]/25" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 2H8.828a2 2 0 00-1.414.586L6.293 3.707A1 1 0 015.586 4H4zm6 3a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>
                                <input type="file" name="photo" accept="image/*" capture="environment" class="hidden"
                                       @change="photoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                            </label>
                        </div>
                        <div class="flex justify-end pt-2"><x-primary-button type="submit">Registrar entrega</x-primary-button></div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</x-admin-layout>
