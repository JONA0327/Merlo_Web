<x-admin-layout active="pagos" title="Pagos">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Pagos</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Tarjeta, OXXO, SPEI y transferencia bancaria. Pega aquí el concepto de una transferencia recibida para encontrar la reservación.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Franja semanal — elige un día para ver solo sus pagos, en vez de
         la lista completa de siempre. Oculta mientras se busca por
         referencia/código, que es "encuéntrame esto sin importar la
         fecha". --}}
    @unless ($filters['q'])
        <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.pagos.index', ['inicio' => $weekStart->copy()->subWeek()->toDateString()]) }}" class="text-sm font-semibold text-[#8C1D2B] hover:text-[#6F1622]">&larr; Semana anterior</a>
                <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $weekStart->format('d/m/Y') }} – {{ $weekStart->copy()->addDays(6)->format('d/m/Y') }}</p>
                <a href="{{ route('admin.pagos.index', ['inicio' => $weekStart->copy()->addWeek()->toDateString()]) }}" class="text-sm font-semibold text-[#8C1D2B] hover:text-[#6F1622]">Semana siguiente &rarr;</a>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                @php $weekdayLabels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']; @endphp
                @foreach ($days as $day)
                    <a href="{{ route('admin.pagos.index', ['fecha' => $day['date']->toDateString()]) }}" class="flex flex-col items-center gap-1 rounded-xl px-2 py-2.5 text-center transition-colors {{ $day['isSelected'] ? 'bg-[#8C1D2B] text-white' : ($day['isPast'] ? 'bg-black/[0.02] text-[#2B1113]/50 hover:bg-black/5' : 'bg-[#FFFBF6] text-[#2B1113] hover:bg-black/5') }}">
                        <span class="text-[10px] font-bold uppercase tracking-wide {{ $day['isSelected'] ? 'text-white/70' : '' }}">{{ $weekdayLabels[$day['date']->dayOfWeek] }}</span>
                        <span class="text-sm font-bold">{{ $day['date']->format('d/m') }}</span>
                        @if ($day['count'] > 0)
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $day['isSelected'] ? 'bg-white/20 text-white' : 'bg-[#8C1D2B]/10 text-[#8C1D2B]' }}">{{ $day['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endunless

    {{-- Totals --}}
    <div class="mb-6 grid grid-cols-1 gap-3 {{ isset($totals['estimated']) ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
        <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Cobrado</p>
            <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-[#15803D]">${{ number_format($totals['completed'], 2) }} MXN</p>
        </div>
        <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Pendiente</p>
            <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-[#A16207]">${{ number_format($totals['pending'], 2) }} MXN</p>
        </div>
        @if (isset($totals['estimated']))
            <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Total estimado del día</p>
                <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-[#2B1113]">${{ number_format($totals['estimated'], 2) }} MXN</p>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.pagos.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        @if ($selectedDate)
            <input type="hidden" name="fecha" value="{{ $selectedDate->toDateString() }}">
        @endif
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Buscar</span>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Charge ID, ticket code, referencia, nombre, email o teléfono" class="mt-1 w-72 rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
        </label>
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Estado</span>
            <select name="status" class="mt-1 rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                <option value="">Todos</option>
                @foreach (['pending' => 'Pendiente', 'completed' => 'Completado', 'failed' => 'Fallido', 'refunded' => 'Reembolsado', 'chargeback' => 'Contracargo'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Método</span>
            <select name="method" class="mt-1 rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                <option value="">Todos</option>
                @foreach (['card' => 'Tarjeta', 'oxxo' => 'OXXO', 'spei' => 'SPEI', 'transfer' => 'Transferencia', 'cash' => 'Efectivo'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['method'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6F1622]">Filtrar</button>
        @if ($filters['status'] || $filters['method'] || $filters['q'])
            <a href="{{ route('admin.pagos.index') }}" class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-[#2B1113]/60 ring-1 ring-black/10 hover:bg-black/5">Limpiar</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-3xl bg-white ring-1 ring-black/5 shadow-sm">
        <table class="min-w-full divide-y divide-black/5">
            <thead class="bg-[#FFFBF6]">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Folio</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Cliente</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Viaje</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Método</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Total</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Estado</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Fecha</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                @forelse ($payments as $payment)
                    <tr class="hover:bg-[#FFFBF6]/60">
                        <td class="px-4 py-3 text-sm">
                            <p class="font-mono text-xs text-[#2B1113]/60">#{{ $payment->id }}</p>
                            <p class="font-mono text-[10px] text-[#2B1113]/40">{{ $payment->ticket_code }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <p class="font-bold text-[#2B1113]">{{ $payment->customer_display_name }}</p>
                            <p class="text-xs text-[#2B1113]/50">{{ $payment->customer_display_email }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <p class="text-[#2B1113]">{{ $payment->landingRoute?->from }} → {{ $payment->landingRoute?->to }}</p>
                            <p class="text-xs text-[#2B1113]/50">{{ $payment->landingRoute?->day?->format('d/m/Y') ?? '—' }} · {{ $payment->trip_type_label }}</p>
                            @php $allSeats = collect([$payment->seat?->label])->merge($payment->groupSeats->pluck('seat.label'))->filter(); @endphp
                            <p class="mt-0.5 text-xs text-[#2B1113]/50">
                                {{ $allSeats->count() }} asiento{{ $allSeats->count() === 1 ? '' : 's' }}: {{ $allSeats->implode(', ') }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center rounded-lg bg-[#FFFBF6] px-2 py-1 text-xs font-bold text-[#2B1113] ring-1 ring-black/10">
                                {{ $payment->payment_method_label }}
                            </span>
                            @if ($payment->payment_method_detail)
                                <p class="mt-1 text-[10px] text-[#2B1113]/50">{{ $payment->payment_method_detail }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-[#2B1113]">
                            ${{ number_format($payment->total, 2) }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $statusColors = [
                                    'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'failed' => 'bg-red-50 text-red-700 ring-red-200',
                                    'refunded' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                    'chargeback' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                ];
                                $statusLabels = [
                                    'completed' => 'Completado',
                                    'pending' => 'Pendiente',
                                    'failed' => 'Fallido',
                                    'refunded' => 'Reembolsado',
                                    'chargeback' => 'Contracargo',
                                ];
                                // Visual only — nothing changes in the DB. A
                                // reservation still sitting pending once its
                                // own trip day already happened never got
                                // resolved (paid or scanned/checked-in), so
                                // it reads differently from a normal
                                // "pendiente" that's still actionable today.
                                $tripDay = $payment->landingRoute?->day ?? $payment->travel_date;
                                $isPastDue = $payment->payment_status === 'pending' && $tripDay?->lt(today());
                            @endphp
                            @if ($isPastDue)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600 ring-1 ring-slate-200">
                                    Fuera de tiempo
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 {{ $statusColors[$payment->payment_status] ?? 'bg-zinc-50 text-zinc-700 ring-zinc-200' }}">
                                    {{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-[#2B1113]/60">
                            {{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.pagos.show', $payment) }}" class="rounded-lg bg-[#FFFBF6] px-2.5 py-1 text-[11px] font-bold text-[#8C1D2B] ring-1 ring-black/10 hover:bg-[#8C1D2B]/5">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-[#2B1113]/50">No hay pagos que coincidan con los filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-admin-layout>
