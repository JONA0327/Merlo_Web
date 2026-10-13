<x-admin-layout :active="'guias'" :title="'Guías'">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Guías</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Aparta asientos por fecha antes de que el viaje exista en el sistema. En cuanto se abra el viaje real para esa fecha, los asientos apartados aquí se vinculan automáticamente.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.guias.historico') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                Ver histórico
            </a>
            <a href="{{ route('admin.guias.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                Nueva guía
            </a>
        </div>
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

    {{-- ===================== Vista semanal — un día por columna, con una
         pestaña por viaje que corre ese día (recurrentes auto-detectados
         + guías manuales cuyo rango cubra la fecha). Cada pestaña lleva
         directo a la pantalla de apartar de siempre, sin cambios. ===================== --}}
    <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('admin.guias.index', ['inicio' => $weekStart->copy()->subWeek()->toDateString()]) }}" class="text-sm font-semibold text-[#8C1D2B] hover:text-[#6F1622]">&larr; Semana anterior</a>
            <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $weekStart->format('d/m/Y') }} – {{ $weekStart->copy()->addDays(6)->format('d/m/Y') }}</p>
            <a href="{{ route('admin.guias.index', ['inicio' => $weekStart->copy()->addWeek()->toDateString()]) }}" class="text-sm font-semibold text-[#8C1D2B] hover:text-[#6F1622]">Semana siguiente &rarr;</a>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
            @php $weekdayLabels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']; @endphp
            @foreach ($days as $day)
                <div class="rounded-2xl border border-black/5 {{ $day['isPast'] ? 'bg-black/[0.02]' : 'bg-[#FFFBF6]' }} p-3">
                    <div class="flex items-center justify-between gap-1.5">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[#2B1113]/50">{{ $weekdayLabels[$day['date']->dayOfWeek] }} {{ $day['date']->format('d/m') }}</p>
                        @if ($day['isPast'])
                            <span class="shrink-0 rounded-full bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-600">Cerrado</span>
                        @endif
                    </div>
                    @if ($day['tabs']->isEmpty())
                        <p class="mt-2 text-xs text-[#2B1113]/40">Sin viajes programados</p>
                    @else
                        <div class="mt-2 flex flex-col gap-1.5">
                            @foreach ($day['tabs'] as $tab)
                                @php
                                    // Per-trip optional color (Viajes → Color en Guías).
                                    $tabColors = [
                                        'orange' => ['past' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-300 hover:ring-orange-500', 'open' => 'bg-orange-100 text-orange-900 ring-1 ring-orange-400 hover:ring-orange-600'],
                                        'blue' => ['past' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-300 hover:ring-sky-500', 'open' => 'bg-sky-100 text-sky-900 ring-1 ring-sky-400 hover:ring-sky-600'],
                                        'green' => ['past' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300 hover:ring-emerald-500', 'open' => 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-400 hover:ring-emerald-600'],
                                        'purple' => ['past' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-300 hover:ring-purple-500', 'open' => 'bg-purple-100 text-purple-900 ring-1 ring-purple-400 hover:ring-purple-600'],
                                        'pink' => ['past' => 'bg-pink-50 text-pink-700 ring-1 ring-pink-300 hover:ring-pink-500', 'open' => 'bg-pink-100 text-pink-900 ring-1 ring-pink-400 hover:ring-pink-600'],
                                        'red' => ['past' => 'bg-red-50 text-red-700 ring-1 ring-red-300 hover:ring-red-500', 'open' => 'bg-red-100 text-red-900 ring-1 ring-red-400 hover:ring-red-600'],
                                    ];
                                    $tabColor = $tabColors[$tab['color'] ?? ''] ?? null;
                                @endphp
                                @if ($tab['active'] && $day['isPast'])
                                    {{-- Ya pasó: apartar está cerrado, pero el día sigue
                                         siendo consultable (show() ya deja ver la lista de
                                         quién viajó, solo bloquea el formulario) — mismo
                                         link, solo con estilo "solo lectura". --}}
                                    <a href="{{ route('admin.guias.show', $tab['guide']) }}?fecha={{ $day['date']->toDateString() }}" title="Fecha pasada — solo consulta" class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-colors {{ $tabColor ? $tabColor['past'] : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 hover:ring-slate-400' }}">
                                        <span class="truncate">{{ $tab['label'] }}</span>
                                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    </a>
                                @elseif ($tab['active'])
                                    <a href="{{ route('admin.guias.show', $tab['guide']) }}?fecha={{ $day['date']->toDateString() }}" class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-colors {{ $tabColor ? $tabColor['open'] : 'bg-white text-[#2B1113] ring-1 ring-black/10 hover:ring-[#8C1D2B]/40' }}">
                                        <span class="truncate">{{ $tab['label'] }}</span>
                                        @if ($tab['pending_count'] > 0)
                                            <span class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">{{ $tab['pending_count'] }}</span>
                                        @endif
                                    </a>
                                @else
                                    <span title="No corre este día" class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-bold cursor-not-allowed bg-black/[0.03] text-[#2B1113]/30 ring-1 ring-black/5">
                                        <span class="truncate line-through decoration-[#2B1113]/20">{{ $tab['label'] }}</span>
                                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Todas las guías</h3>
        @if ($guides->isEmpty())
            <div class="mt-4 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                No hay guías todavía. Crea una para empezar a apartar asientos por adelantado.
            </div>
        @else
            <div class="mt-4 overflow-x-auto rounded-2xl ring-1 ring-black/5">
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
                                            <form method="POST" action="{{ route('admin.guias.destroy', $guide) }}" data-confirm="¿Eliminar esta guía? Se cancelarán {{ $guide->pending_count }} apartado(s) pendiente(s) que aún no se hayan vinculado a un viaje real.">
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
