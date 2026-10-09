<x-admin-layout active="destinations" title="Destinos">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Destinos</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Ciudades que la empresa cubre. Se muestran como select en el formulario de crear/editar viaje.</p>
        </div>
        <a href="{{ route('admin.destinations.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
            Agregar destino
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="rounded-3xl bg-white ring-1 ring-black/5 shadow-sm overflow-hidden">
        @if ($destinations->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm text-[#2B1113]/60">Aún no hay destinos. Crea el primero con el botón <strong>Agregar destino</strong> arriba.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-black/5 text-sm">
                <thead class="bg-[#FFFBF6] text-left text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3 text-right">Viajes como origen</th>
                        <th class="px-4 py-3 text-right">Viajes como destino</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 bg-white">
                    @foreach ($destinations as $d)
                        <tr class="hover:bg-[#FFFBF6]/60 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $d->name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($d->code)
                                    <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 font-mono text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $d->code }}</span>
                                @else
                                    <span class="text-xs text-[#2B1113]/40">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $d->trips_from_count ?? 0 }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $d->trips_to_count ?? 0 }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($d->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">Activo</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.destinations.edit', $d) }}" class="rounded-lg bg-[#FFFBF6] px-2.5 py-1 text-[11px] font-bold text-[#8C1D2B] ring-1 ring-black/10 hover:bg-[#8C1D2B]/5">Editar</a>
                                    <form method="POST" action="{{ route('admin.destinations.destroy', $d) }}" data-confirm="¿Eliminar este destino?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-admin-layout>