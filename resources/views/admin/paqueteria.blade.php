<x-admin-layout active="paqueteria" title="Paquetería">
    @php
        $statusMeta = [
            'sin_asignar' => ['label' => 'Sin asignar', 'class' => 'bg-black/5 text-[#2B1113]/60'],
            'recolectado' => ['label' => 'Recolectado', 'class' => 'bg-[#F5B301]/15 text-[#8C6B00]'],
            'en_recorrido' => ['label' => 'En recorrido', 'class' => 'bg-blue-100 text-blue-700'],
            'entregado' => ['label' => 'Entregado', 'class' => 'bg-emerald-100 text-emerald-700'],
            'no_entregado' => ['label' => 'No entregado', 'class' => 'bg-red-100 text-red-700'],
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Paquetería</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Registra y da seguimiento a los envíos de paquetería.</p>
        </div>
        <a href="{{ route('admin.paqueteria.qr.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            Generar QR
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 inline-flex rounded-xl bg-black/5 p-1">
        <a href="{{ route('admin.paqueteria', ['tab' => 'activos']) }}" class="rounded-lg px-4 py-2 text-sm font-bold transition-colors {{ $tab === 'activos' ? 'bg-white text-[#2B1113] shadow-sm' : 'text-[#2B1113]/50' }}">
            Activos
        </a>
        <a href="{{ route('admin.paqueteria', ['tab' => 'historial']) }}" class="rounded-lg px-4 py-2 text-sm font-bold transition-colors {{ $tab === 'historial' ? 'bg-white text-[#2B1113] shadow-sm' : 'text-[#2B1113]/50' }}">
            Historial
        </a>
    </div>
    @if ($tab === 'historial')
        <p class="-mt-4 mb-6 text-xs text-[#2B1113]/50">Registro permanente de paquetes entregados o no entregados. Nunca se borra, ni al generar nuevos lotes de QR.</p>
    @endif

    <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <form method="POST" action="{{ route('admin.paqueteria.paquetes.lookup') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input
                type="text"
                name="code"
                autofocus
                placeholder="Escanea o escribe el código del paquete"
                class="flex-1 rounded-xl border-black/10 uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case focus:border-[#8C1D2B] focus:ring-[#8C1D2B]"
            >
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2B1113] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#2B1113]/90 transition-colors">
                Buscar
            </button>
        </form>
        @error('code')
            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.paqueteria', ['tab' => $tab]) }}" class="rounded-full px-4 py-2 text-xs font-bold transition-colors {{ ! $status ? 'bg-[#8C1D2B] text-white' : 'bg-white text-[#2B1113]/60 ring-1 ring-black/5' }}">
            Todos
        </a>
        @foreach ($statuses as $option)
            <a href="{{ route('admin.paqueteria', ['tab' => $tab, 'status' => $option]) }}" class="rounded-full px-4 py-2 text-xs font-bold transition-colors {{ $status === $option ? 'bg-[#8C1D2B] text-white' : 'bg-white text-[#2B1113]/60 ring-1 ring-black/5' }}">
                {{ $statusMeta[$option]['label'] }}
            </a>
        @endforeach
    </div>

    @if ($packages->isEmpty())
        <x-empty-state
            title="No hay paquetes en esta vista"
            :description="$tab === 'historial' ? 'Aquí aparecerán los paquetes entregados o no entregados — quedan guardados aunque generes nuevos lotes de QR.' : 'Genera un lote de códigos QR o escanea uno existente para empezar a registrar envíos.'"
        >
            <x-slot name="icon">
                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
            </x-slot>
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
            <table class="min-w-full divide-y divide-black/5 text-sm">
                <thead class="bg-black/[0.02]">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Código</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Cliente</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Estado</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">{{ $tab === 'historial' ? 'Entregado' : 'Recolectado' }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @foreach ($packages as $package)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-[#2B1113]">{{ $package->tracking_code }}</td>
                            <td class="px-5 py-3 text-[#2B1113]/80">
                                {{ $package->displayClientName() ?? '—' }}
                                @if ($package->isGrouped())
                                    <span class="ml-1 text-xs text-[#2B1113]/40">(envío conjunto)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusMeta[$package->status]['class'] }}">
                                    {{ $statusMeta[$package->status]['label'] }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-[#2B1113]/60">
                                {{ ($tab === 'historial' ? $package->delivered_at : $package->collected_at)?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.paqueteria.paquetes.show', $package) }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">Ver</a>
                                    @if ($package->status === \App\Models\Package::STATUS_SIN_ASIGNAR)
                                        <form method="POST" action="{{ route('admin.paqueteria.paquetes.destroy', $package) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-700" onclick="return confirm('¿Eliminar el código {{ $package->tracking_code }}? La etiqueta impresa dejará de funcionar.')">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $packages->links() }}
        </div>
    @endif
</x-admin-layout>
