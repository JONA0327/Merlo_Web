<x-admin-layout active="paqueteria" title="{{ $client->name }} · Paquetería">
    @php
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $statusLabel = ['recolectado' => 'Recolectado', 'en_recorrido' => 'En recorrido', 'entregado' => 'Entregado', 'no_entregado' => 'No entregado'];
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.paqueteria.clientes') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Clientes</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">{{ $client->name }}</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">{{ $client->phone_formatted }}</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded-2xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Saldos pendientes</h3>
        @forelse ($pendingUnits as $u)
            @php
                $isGroup = $u instanceof \App\Models\PackageGroup;
                $folios = $isGroup ? $u->packages->sortBy('folio_number')->pluck('folio_label')->implode(', ') : ($u->folio_label ?? 'Sin folio · '.($u->delivered_at?->format('d/m/Y') ?? ''));
            @endphp
            <form method="POST" action="{{ route('admin.paqueteria.clientes.pay', $client) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-black/5 pt-4">
                @csrf
                <input type="hidden" name="unit" value="{{ $isGroup ? 'group' : 'package' }}:{{ $u->getKey() }}">
                <div class="min-w-48 flex-1">
                    <p class="text-sm font-semibold text-[#2B1113]">{{ $folios }}</p>
                    <p class="text-xs text-[#2B1113]/60">Total {{ $money($u->billedAmount()) }} · pagado {{ $money($u->amount_paid) }} · <span class="font-semibold text-red-600">debe {{ $money($u->pendingAmount()) }}</span></p>
                </div>
                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $u->pendingAmount() }}" value="{{ $u->pendingAmount() }}" class="w-32 rounded-xl border-black/10 text-sm focus:border-[#8C1D2B] focus:ring-[#8C1D2B]">
                <x-primary-button type="submit">Registrar pago</x-primary-button>
            </form>
        @empty
            <p class="mt-2 text-sm text-[#2B1113]/50">Sin saldos pendientes.</p>
        @endforelse
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-black/5 shadow-sm">
        <h3 class="px-5 pt-5 font-[Poppins] text-lg font-bold text-[#2B1113]">Historial de paquetes</h3>
        <table class="mt-3 w-full text-sm">
            <thead class="text-xs uppercase text-[#2B1113]/50 border-b border-black/5">
                <tr><th class="px-5 py-3 text-left">Folio</th><th class="px-5 py-3 text-left">Estado</th><th class="px-5 py-3 text-left">Recolectado</th><th class="px-5 py-3 text-left">Entregado</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($packages as $p)
                    <tr class="border-b border-black/5">
                        <td class="px-5 py-3 font-semibold text-[#2B1113]">{{ $p->folio_label ?? 'Sin folio' }}</td>
                        <td class="px-5 py-3">
                            @if (in_array($p->status, ['recolectado', 'en_recorrido'], true))
                                <span class="rounded-full bg-[#F5B301]/15 px-2.5 py-1 text-xs font-bold text-[#8C6B00]">{{ $statusLabel[$p->status] }}</span>
                            @else
                                <span class="text-[#2B1113]/70">{{ $statusLabel[$p->status] ?? $p->status }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-[#2B1113]/70">{{ $p->collected_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-3 text-[#2B1113]/70">{{ $p->delivered_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('admin.paqueteria.paquetes.show', $p) }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">{{ in_array($p->status, ['recolectado', 'en_recorrido'], true) ? 'Ver / entregar' : 'Ver' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-[#2B1113]/50">Sin paquetes todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
