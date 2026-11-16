<x-admin-layout active="paqueteria" title="Historial · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Historial de entregas</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Registro permanente de lo ya entregado, con el cliente de cada envío. Nunca se borra, ni al generar nuevos lotes de folios.</p>
    </div>

    <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="q" value="{{ $search }}" autofocus placeholder="Buscar por cliente…" class="flex-1 rounded-xl border-black/10 focus:border-[#8C1D2B] focus:ring-[#8C1D2B]">
            <input type="hidden" name="folio" value="{{ $folio }}">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2B1113] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#2B1113]/90 transition-colors">Buscar</button>
        </form>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['todos' => 'Todos', 'con' => 'Con folio', 'sin' => 'Sin folio'] as $value => $label)
                <a
                    href="{{ route('admin.paqueteria.historial', ['q' => $search, 'folio' => $value]) }}"
                    class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors {{ $folio === $value ? 'bg-[#8C1D2B] text-white' : 'bg-black/5 text-[#2B1113]/60 hover:bg-black/10' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if ($packages->isEmpty())
        <x-empty-state
            title="Aún no hay entregas"
            description="Aquí aparecerán los paquetes conforme se vayan entregando."
        >
            <x-slot name="icon">
                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
            </x-slot>
        </x-empty-state>
    @else
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
            <table class="min-w-full divide-y divide-black/5 text-sm">
                <thead class="bg-black/[0.02]">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Folio</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Cliente</th>
                        <th class="px-5 py-3 text-left font-bold text-[#2B1113]/60">Entregado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @foreach ($packages as $package)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-[#2B1113]">{{ $package->folio_label ?? ($package->phone || $package->displayPhone() ? $package->tracking_code : 'Sin folio') }}</td>
                            <td class="px-5 py-3 text-[#2B1113]/80">
                                {{ $package->displayClientName() ?? '—' }}
                                @if ($package->isGrouped())
                                    <span class="ml-1 text-xs text-[#2B1113]/40">(envío conjunto)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-[#2B1113]/60">{{ $package->delivered_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.paqueteria.paquetes.show', $package) }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">Ver</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $packages->links() }}</div>
    @endif
</x-admin-layout>
