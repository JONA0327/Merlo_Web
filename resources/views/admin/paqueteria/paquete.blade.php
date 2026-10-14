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
                    <div class="mt-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Foto de evidencia</p>
                        <a href="{{ route('admin.paqueteria.paquetes.photo', $package) }}" target="_blank" class="mt-2 block w-40 overflow-hidden rounded-xl ring-1 ring-black/5">
                            <img src="{{ route('admin.paqueteria.paquetes.photo', $package) }}" alt="Evidencia de {{ $package->folio_label }}" class="h-40 w-40 object-cover">
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
