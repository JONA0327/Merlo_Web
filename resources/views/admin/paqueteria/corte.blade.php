<x-admin-layout active="paqueteria" title="Corte del día · Paquetería">
    @php $money = fn ($v) => '$'.number_format((float) $v, 2); @endphp

    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Corte de paquetería</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Lo cobrado en el día se manda a Control Reportes como ingreso de paquetería, y lo que se dio de eso como egreso.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <form method="GET" class="mb-6 flex items-end gap-3">
        <label class="block">
            <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Fecha</span>
            <input type="date" name="fecha" value="{{ $date->format('Y-m-d') }}" class="mt-1 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm">
        </label>
        <button type="submit" class="rounded-xl bg-[#2B1113]/5 px-4 py-2 text-xs font-bold text-[#2B1113] hover:bg-[#2B1113]/10">Ver</button>
        <a href="{{ route('admin.paqueteria.corte.imprimir', ['fecha' => $date->format('Y-m-d')]) }}" target="_blank" class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">Imprimir lista</a>
    </form>

    <div class="rounded-2xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-2xl font-bold text-[#2B1113]">{{ $money($payments->sum('amount')) }} <span class="text-sm font-medium text-[#2B1113]/50">· {{ $payments->count() }} cobro(s)</span></p>
            @if ($corte?->sent_at)
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Enviado a Control {{ $corte->sent_at->format('d/m/Y H:i') }} ({{ $money($corte->total_amount) }})</span>
            @endif
        </div>

        <ul class="mt-4 divide-y divide-black/5 text-sm">
            @forelse ($payments as $p)
                @php
                    $unit = $p->payable;
                    $folios = $unit instanceof \App\Models\PackageGroup ? $unit->packages->sortBy('folio_number')->pluck('folio_label')->implode(', ') : $unit?->folio_label;
                @endphp
                <li class="flex justify-between py-2"><span class="text-[#2B1113]/70">{{ $p->paid_at->format('H:i') }} · {{ $unit?->client_name }} · {{ $folios ?: 'Sin folio' }}</span><span class="font-semibold text-[#2B1113]">{{ $money($p->amount) }}</span></li>
            @empty
                <li class="py-4 text-[#2B1113]/50">No hubo cobros este día.</li>
            @endforelse
        </ul>

        {{-- ===== Egresos: lo que se dio de lo recaudado ===== --}}
        @php
            $cobrado = (float) $payments->sum('amount');
            $egresado = (float) $expenses->sum('amount');
            $sinEnviar = $corte?->sent_at && (abs($egresado - (float) $corte->expenses_amount) > 0.001 || $expenses->count() !== (int) $corte->expenses_count);
        @endphp
        <div class="mt-6 border-t border-black/5 pt-5">
            <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Egresos del día</h3>
            <p class="mt-1 text-xs text-[#2B1113]/50">Lo que se entregó o se gastó de lo recaudado (concepto y cantidad). Se manda a Control como egreso, empatado con el ingreso de este día.</p>

            <ul class="mt-3 divide-y divide-black/5 text-sm">
                @forelse ($expenses as $e)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="text-[#2B1113]/70">{{ $e->concept }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold text-red-600">-{{ $money($e->amount) }}</span>
                            <form method="POST" action="{{ route('admin.paqueteria.corte.expenses.destroy', $e) }}" onsubmit="return confirm('¿Quitar este egreso?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-[#2B1113]/40 hover:text-red-600">Quitar</button>
                            </form>
                        </span>
                    </li>
                @empty
                    <li class="py-3 text-[#2B1113]/50">Sin egresos este día.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('admin.paqueteria.corte.expenses.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="fecha" value="{{ $date->format('Y-m-d') }}">
                <label class="block min-w-56 flex-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Concepto</span>
                    <input type="text" name="concept" maxlength="150" required placeholder="Ej. Gasolina, pago a chofer…" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Cantidad</span>
                    <input type="number" name="amount" step="0.01" min="0.01" required class="mt-1 w-36 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm">
                </label>
                <button type="submit" class="rounded-xl bg-[#2B1113]/5 px-4 py-2 text-xs font-bold text-[#2B1113] hover:bg-[#2B1113]/10">Agregar egreso</button>
            </form>
            @error('concept')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('amount')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror

            <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Ingresos</dt><dd class="mt-1 text-lg font-bold text-emerald-700">{{ $money($cobrado) }}</dd></div>
                <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Egresos</dt><dd class="mt-1 text-lg font-bold text-red-600">{{ $money($egresado) }}</dd></div>
                <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Queda</dt><dd class="mt-1 text-lg font-bold text-[#2B1113]">{{ $money($cobrado - $egresado) }}</dd></div>
            </dl>
            @if ($sinEnviar)
                <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">Hay egresos distintos a los que ya se enviaron a Control: reenvía el corte.</p>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.paqueteria.corte.store') }}" class="mt-5" onsubmit="return confirm('¿Enviar el corte del {{ $date->format('d/m/Y') }} a Control Reportes?')">
            @csrf
            <input type="hidden" name="fecha" value="{{ $date->format('Y-m-d') }}">
            <button type="submit" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:opacity-90">{{ $corte?->sent_at ? 'Reenviar corte' : 'Enviar corte a Control' }}</button>
        </form>
    </div>
</x-admin-layout>
