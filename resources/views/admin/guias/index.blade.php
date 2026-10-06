<x-admin-layout :active="'guias'" :title="'Guías'">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Guías</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Aparta asientos por fecha antes de que el viaje exista en el sistema. En cuanto se abra el viaje real para esa fecha, los asientos apartados aquí se vinculan automáticamente.</p>
        </div>
        <a href="{{ route('admin.guias.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
            Nueva guía
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        @if ($guides->isEmpty())
            <div class="rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                No hay guías todavía. Crea una para empezar a apartar asientos por adelantado.
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl ring-1 ring-black/5">
                <table class="min-w-full divide-y divide-black/5 text-sm">
                    <thead class="bg-[#FFFBF6] text-left text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">
                        <tr>
                            <th class="px-4 py-3">Ruta</th>
                            <th class="px-4 py-3">Plantilla</th>
                            <th class="px-4 py-3">Rango de fechas</th>
                            <th class="px-4 py-3">Apartados</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-black/5 bg-white">
                        @foreach ($guides as $guide)
                            <tr class="hover:bg-[#FFFBF6]/60 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $guide->from }} → {{ $guide->to }}</p>
                                </td>
                                <td class="px-4 py-3 text-[#2B1113]/80">{{ $guide->busUnit->name }}</td>
                                <td class="px-4 py-3 text-[#2B1113]/80">{{ $guide->date_from->format('d/m/Y') }} – {{ $guide->date_to->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                        {{ $guide->pending_count }} pendiente{{ $guide->pending_count === 1 ? '' : 's' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex flex-col items-stretch gap-1.5">
                                        <a href="{{ route('admin.guias.show', $guide) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                                            Apartar
                                        </a>
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('admin.guias.edit', $guide) }}" class="text-[11px] font-semibold text-[#2B1113]/60 hover:text-[#2B1113]">Editar</a>
                                            <form method="POST" action="{{ route('admin.guias.destroy', $guide) }}" onsubmit="return confirm('¿Eliminar esta guía? Se cancelarán {{ $guide->pending_count }} apartado(s) pendiente(s) que aún no se hayan vinculado a un viaje real.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[11px] font-semibold text-red-600 hover:text-red-700">Eliminar</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin-layout>
