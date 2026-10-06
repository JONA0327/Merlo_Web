<x-admin-layout active="asientos" title="Apartar asientos">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Apartar asientos</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Reserva asientos en nombre de un cliente desde la ventanilla o por teléfono. Quedan como "Pendientes" hasta que se paguen; cuando los liberes al cliente, pulsa <strong>Enviar boleto</strong>.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Viajes disponibles</h3>

        @if ($routes->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                No hay viajes activos con mapa de asientos configurado todavía.
            </div>
        @else
            <div class="mt-6 overflow-x-auto rounded-2xl ring-1 ring-black/5">
                <table class="min-w-full divide-y divide-black/5 text-sm">
                    <thead class="bg-[#FFFBF6] text-left text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">
                        <tr>
                            <th class="px-4 py-3">Viaje</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Apartados</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-black/5 bg-white">
                        @foreach ($routes as $route)
                            <tr class="hover:bg-[#FFFBF6]/60 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $route->from }} → {{ $route->to }}</p>
                                    <p class="text-xs text-[#2B1113]/60">{{ $route->duration }} · {{ $route->formatted_price }}</p>
                                </td>
                                <td class="px-4 py-3 text-[#2B1113]/80">
                                    {{ $route->day ? $route->day->format('d/m/Y') : 'Sin fecha' }}
                                    <span class="text-xs text-[#2B1113]/50">· {{ $route->departure_time_formatted ?? 'Sin horario' }}</span>
                                    @if ($route->hasEnded())
                                        <span class="ml-1 inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700">Cerrado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                            {{ $route->pending_reservations_count }} pendiente{{ $route->pending_reservations_count === 1 ? '' : 's' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                                            {{ $route->sent_reservations_count }} enviado{{ $route->sent_reservations_count === 1 ? '' : 's' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($route->hasSeatMap())
                                        <div class="inline-flex flex-col items-stretch gap-1.5">
                                            <a href="{{ route('admin.asientos.show', $route) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                                                {{ $route->hasEnded() ? 'Ver apartados' : 'Apartar' }}
                                            </a>
                                            @unless ($route->hasEnded())
                                                <a href="{{ route('admin.asientos.availability', $route) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.75a.75.75 0 00-1.5 0v.541c-.42.087-.808.222-1.147.414-1.012.572-1.353 1.61-.786 2.629.317.567.875.92 1.446 1.148.453.18.881.31 1.237.45v1.698c-.42-.087-.808-.222-1.147-.414-.591-.333-1.105-.86-1.244-1.546a.75.75 0 00-1.48.253c.243 1.332 1.156 2.247 2.184 2.785.378.197.74.327 1.087.404v.518a.75.75 0 001.5 0v-.518c.42-.087.808-.222 1.147-.414 1.012-.572 1.353-1.61.786-2.629-.317-.567-.875-.92-1.446-1.148a10.21 10.21 0 00-1.237-.45v-1.698c.42.087.808.222 1.147.414.591.333 1.105.86 1.244 1.546a.75.75 0 001.48-.253c-.243-1.332-1.156-2.247-2.184-2.785A4.59 4.59 0 0010.75 6.79V6.25z" clip-rule="evenodd"/></svg>
                                                    Disponibilidad
                                                </a>
                                            @endunless
                                            <a href="{{ route('admin.asientos.manifest', $route) }}" target="_blank" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.097 1.126.153A2.212 2.212 0 0118 8.653v4.097A2.25 2.25 0 0115.75 15h-.241l.305 1.984A1.75 1.75 0 0113.84 19H6.16a1.75 1.75 0 01-1.973-2.016L4.491 15H4.25A2.25 2.25 0 012 12.75V8.653c0-1.082.775-2.034 1.874-2.198.374-.056.75-.107 1.126-.153V2.75zm8.5 3.397V2.75a.25.25 0 00-.25-.25h-6.5a.25.25 0 00-.25.25v3.397c1.126-.1 2.264-.148 3.5-.148s2.374.049 3.5.148zm-7 9.853l.414-2.691c1.364-.1 2.73-.15 4.086-.15 1.356 0 2.722.05 4.086.15l.414 2.691a.25.25 0 01-.247.287H6.747a.25.25 0 01-.247-.287z" clip-rule="evenodd"/></svg>
                                                Imprimir lista
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-xs font-semibold text-[#2B1113]/40">Sin mapa</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin-layout>
