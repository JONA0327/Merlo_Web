<x-admin-layout active="paqueteria" title="Paquetes del día · Paquetería">
    @php
        $statusMeta = [
            'recolectado' => ['label' => 'Recolectado', 'class' => 'bg-[#F5B301]/15 text-[#8C6B00]'],
            'entregado' => ['label' => 'Entregado', 'class' => 'bg-emerald-100 text-emerald-700'],
        ];
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Paquetes del día</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Los paquetes recolectados ese día — recolectado al registrarse, entregado cuando se entrega.</p>
    </div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex items-end gap-3">
            <label class="block">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Fecha</span>
                <input type="date" name="fecha" value="{{ $date->format('Y-m-d') }}" class="mt-1 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm">
            </label>
            <button type="submit" class="rounded-xl bg-[#2B1113]/5 px-4 py-2 text-xs font-bold text-[#2B1113] hover:bg-[#2B1113]/10">Ver</button>
        </form>

        <form method="POST" action="{{ route('admin.paqueteria.paquetes.lookup') }}" class="flex items-end gap-2">
            @csrf
            <input type="text" name="code" placeholder="Buscar por folio (ej. F-000101)" class="rounded-xl border-black/10 text-sm uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case focus:border-[#8C1D2B] focus:ring-[#8C1D2B]">
            <button type="submit" class="rounded-xl bg-[#2B1113] px-4 py-2 text-xs font-bold text-white hover:bg-[#2B1113]/90">Buscar</button>
        </form>
    </div>
    @error('code')
        <p class="-mt-4 mb-4 text-xs font-semibold text-red-600">{{ $message }}</p>
    @enderror

    @if ($packages->isEmpty())
        <x-empty-state
            title="No hay paquetes ese día"
            description="Elige otra fecha, o registra un envío nuevo para que aparezca aquí."
        >
            <x-slot name="icon">
                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
            </x-slot>
        </x-empty-state>
    @else
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
            <table class="min-w-full divide-y divide-black/5 text-sm">
                <thead class="bg-black/[0.02]">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Folio</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Cliente</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Estado</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Recolectado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @foreach ($packages as $package)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-[#2B1113]">{{ $package->folio_label ?? $package->tracking_code }}</td>
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
                            <td class="px-5 py-3 text-[#2B1113]/60">{{ $package->collected_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.paqueteria.paquetes.show', $package) }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">{{ $package->status === 'recolectado' ? 'Ver / entregar' : 'Ver' }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
