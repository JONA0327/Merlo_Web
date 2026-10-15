<x-admin-layout active="paqueteria" title="Clientes · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Clientes de paquetería</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Historial de envíos y saldos pendientes por cliente (identificado por teléfono).</p>
    </div>

    <form method="GET" class="mb-4">
        <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por nombre o teléfono…" class="w-full sm:w-80 rounded-xl border-black/10 text-sm focus:border-[#8C1D2B] focus:ring-[#8C1D2B]">
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-black/5 shadow-sm">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase text-[#2B1113]/50 border-b border-black/5">
                <tr><th class="px-5 py-3 text-left">Cliente</th><th class="px-5 py-3 text-left">Teléfono</th><th class="px-5 py-3 text-right">Envíos</th><th class="px-5 py-3 text-right">Pendiente de pago</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($clients as $c)
                    <tr class="border-b border-black/5">
                        <td class="px-5 py-3 font-semibold text-[#2B1113]">{{ $c->name }}</td>
                        <td class="px-5 py-3 text-[#2B1113]/70">{{ $c->phone_formatted }}</td>
                        <td class="px-5 py-3 text-right text-[#2B1113]/70">{{ $c->packages_count + $c->groups_count }}</td>
                        <td class="px-5 py-3 text-right font-semibold {{ $c->pending > 0 ? 'text-red-600' : 'text-[#2B1113]/40' }}">${{ number_format($c->pending, 2) }}</td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('admin.paqueteria.clientes.show', $c) }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-[#2B1113]/50">Todavía no hay clientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>
</x-admin-layout>
