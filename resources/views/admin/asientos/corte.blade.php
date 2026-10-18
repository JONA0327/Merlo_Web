<x-admin-layout :active="'guias'" :title="'Corte — '.$trip->from.' → '.$trip->to">
    @php
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $gastos = $lines->where('kind', 'gasto');
        $otrosIngresos = $lines->where('kind', 'ingreso');
        $recaudado = $corte['sold_amount'] + $corte['external_amount'];
        $queda = $recaudado + $otrosIngresos->sum('amount') - $gastos->sum('amount');
        $backUrl = $guide && $trip->day ? route('admin.guias.show', [$guide, 'fecha' => $trip->day->toDateString()]) : route('admin.guias.index');
        $input = 'mt-1 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] outline-none focus:border-[#8C1D2B]';
        $label = 'text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60';
    @endphp

    <div class="mb-6">
        <a href="{{ $backUrl }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver al viaje</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Corte del viaje</h2>
                <p class="mt-1 text-sm text-[#2B1113]/60">{{ $trip->from }} → {{ $trip->to }} · {{ $trip->day?->format('d/m/Y') }}</p>
            </div>
            @if ($corteSaved?->sent_at)
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Enviado a Control {{ $corteSaved->sent_at->format('d/m/Y H:i') }}</span>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($sinEnviar)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">Hay cambios que todavía no están en Control: reenvía el corte al final de la página.</div>
    @endif

    {{-- ===== 1. Ventas (automático) ===== --}}
    <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Ventas</h3>
        <p class="mt-1 text-xs text-[#2B1113]/50">Se calculan solas de las reservas del viaje (pagadas y apartadas, al precio de su categoría).</p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Asientos totales</dt><dd class="mt-1 text-lg font-bold text-[#2B1113]">{{ $corte['total_seats'] }}</dd></div>
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Vendidos</dt><dd class="mt-1 text-lg font-bold text-[#2B1113]">{{ $corte['sold_seats'] + $corte['external_seats'] }}</dd></div>
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Recaudado</dt><dd class="mt-1 text-lg font-bold text-emerald-700">{{ $money($recaudado) }}</dd></div>
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Por cobrar</dt><dd class="mt-1 text-lg font-bold text-amber-700">{{ $corte['por_cobrar_seats'] }} · {{ $money($corte['por_cobrar_amount']) }}</dd></div>
        </dl>

        <div class="mt-5 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <p class="{{ $label }}">Por categoría</p>
                <ul class="mt-2 divide-y divide-black/5 text-sm">
                    @forelse ($corte['by_type'] as $r)
                        <li class="flex justify-between py-1.5"><span class="text-[#2B1113]/70">{{ $r['seats'] }} {{ $r['label'] }}</span><span class="font-semibold">{{ $money($r['amount']) }}</span></li>
                    @empty
                        <li class="py-1.5 text-[#2B1113]/50">Sin ventas.</li>
                    @endforelse
                    @if ($corte['external_seats'] > 0)
                        <li class="flex justify-between py-1.5"><span class="text-[#2B1113]/70">{{ $corte['external_seats'] }} sin apartar en guía</span><span class="font-semibold">{{ $money($corte['external_amount']) }}</span></li>
                    @endif
                </ul>
            </div>
            <div>
                <p class="{{ $label }}">Por forma de pago</p>
                <ul class="mt-2 divide-y divide-black/5 text-sm">
                    @foreach ($corte['by_method'] as $r)
                        <li class="flex justify-between py-1.5"><span class="text-[#2B1113]/70">{{ $r['seats'] }} · {{ $r['label'] }}</span><span class="font-semibold">{{ $money($r['amount']) }}</span></li>
                    @endforeach
                    @if ($corte['abonos_amount'] > 0)
                        <li class="flex justify-between py-1.5"><span class="text-[#2B1113]/70">Abonos recibidos (apartados)</span><span class="font-semibold">{{ $money($corte['abonos_amount']) }}</span></li>
                    @endif
                    @if ($corte['por_cobrar_seats'] > 0)
                        <li class="flex justify-between py-1.5"><span class="text-amber-700">{{ $corte['por_cobrar_seats'] }} por cobrar</span><span class="font-semibold text-amber-700">{{ $money($corte['por_cobrar_amount']) }}</span></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    {{-- ===== 2. Otros conceptos del día ===== --}}
    <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Gastos y otros conceptos del día</h3>
        <p class="mt-1 text-xs text-[#2B1113]/50">Sueldos, casetas, estacionamiento, etc. Si es "cantidad × monto" (ej. 2 × $2,700), escribe la cantidad. Lo que marques como "cuenta en Control" entra a Ingresos y Egresos; lo demás queda solo informativo.</p>

        @foreach ([['Gastos', $gastos, 'text-red-600', '-'], ['Otros ingresos', $otrosIngresos, 'text-emerald-700', '+']] as [$titulo, $items, $color, $signo])
            <p class="mt-4 {{ $label }}">{{ $titulo }}</p>
            <ul class="mt-1 divide-y divide-black/5 text-sm">
                @forelse ($items as $l)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="text-[#2B1113]/80">
                            {{ $l->concept }}
                            @if ((float) $l->quantity !== 1.0)<span class="text-xs text-[#2B1113]/50">({{ rtrim(rtrim(number_format($l->quantity, 2), '0'), '.') }} × {{ $money($l->unit_amount) }})</span>@endif
                            @if ($l->note)<span class="text-xs text-[#2B1113]/50">· {{ $l->note }}</span>@endif
                            @unless ($l->counts)<span class="rounded border border-black/15 px-1 text-[10px] font-semibold text-[#2B1113]/50">informativo</span>@endunless
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold {{ $color }}">{{ $signo }}{{ $money($l->amount) }}</span>
                            <form method="POST" action="{{ route('admin.viajes.corte.lines.destroy', [$trip, $l]) }}" onsubmit="return confirm('¿Quitar este concepto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-[#2B1113]/40 hover:text-red-600">Quitar</button>
                            </form>
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-[#2B1113]/50">Ninguno.</li>
                @endforelse
            </ul>
        @endforeach

        <form method="POST" action="{{ route('admin.viajes.corte.lines.store', $trip) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-black/5 pt-5">
            @csrf
            <label class="block">
                <span class="{{ $label }}">Tipo</span>
                <select name="kind" class="{{ $input }}">
                    <option value="gasto">Gasto</option>
                    <option value="ingreso">Otro ingreso</option>
                </select>
            </label>
            <label class="block min-w-52 flex-1">
                <span class="{{ $label }}">Concepto</span>
                <input type="text" name="concept" maxlength="150" required placeholder="Ej. Sueldo salida, casetas, estacionamiento…" class="{{ $input }} w-full">
            </label>
            <label class="block">
                <span class="{{ $label }}">Cantidad</span>
                <input type="number" name="quantity" step="0.01" min="0.01" value="1" class="{{ $input }} w-24">
            </label>
            <label class="block">
                <span class="{{ $label }}">Monto (c/u)</span>
                <input type="number" name="unit_amount" step="0.01" min="0.01" required class="{{ $input }} w-32">
            </label>
            <label class="block min-w-40 flex-1">
                <span class="{{ $label }}">Nota / referencia</span>
                <input type="text" name="note" maxlength="150" placeholder="Opcional (ej. folio de caseta)" class="{{ $input }} w-full">
            </label>
            <label class="flex items-center gap-2 pb-2 text-xs font-semibold text-[#2B1113]/70">
                <input type="checkbox" name="counts" value="1" checked class="rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]">
                Cuenta en Control
            </label>
            <button type="submit" class="rounded-xl bg-[#2B1113]/5 px-4 py-2 text-xs font-bold text-[#2B1113] hover:bg-[#2B1113]/10">Agregar</button>
        </form>
    </div>

    {{-- ===== 3. Resumen, efectivo y envío ===== --}}
    <form method="POST" action="{{ route('admin.viajes.corte', $trip) }}" class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm"
          onsubmit="return event.submitter?.dataset.confirm ? confirm(event.submitter.dataset.confirm) : true">
        @csrf
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Cierre del día</h3>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Recaudado</dt><dd class="mt-1 text-lg font-bold text-emerald-700">{{ $money($recaudado) }}</dd></div>
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Otros ingresos</dt><dd class="mt-1 text-lg font-bold text-emerald-700">{{ $money($otrosIngresos->sum('amount')) }}</dd></div>
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Gastos</dt><dd class="mt-1 text-lg font-bold text-red-600">{{ $money($gastos->sum('amount')) }}</dd></div>
            <div class="rounded-2xl bg-[#FFFBF6] p-3"><dt class="{{ $label }}">Recaudado − gastos</dt><dd class="mt-1 text-lg font-bold text-[#2B1113]">{{ $money($queda) }}</dd></div>
        </dl>

        <div class="mt-5 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="{{ $label }}">Asientos vendidos sin apartar en guía</span>
                <input type="number" name="external_seats" min="0" max="{{ $corte['free_seats'] + $corte['external_seats'] }}" value="{{ old('external_seats', $corte['external_seats']) }}" class="{{ $input }} w-44">
            </label>
            <label class="block">
                <span class="{{ $label }}">Dinero cobrado de esos asientos</span>
                <input type="number" step="0.01" name="external_amount" min="0" value="{{ old('external_amount', $corte['external_amount']) }}" class="{{ $input }} w-44">
            </label>
            <label class="block">
                <span class="{{ $label }}">Efectivo (lo escribes tú)</span>
                <input type="number" step="0.01" name="cash_amount" min="0" value="{{ old('cash_amount', $corteSaved?->cash_amount) }}" class="{{ $input }} w-40">
            </label>
            <label class="block">
                <span class="{{ $label }}">Contrato de compras</span>
                <select name="contrato_id" class="{{ $input }}">
                    <option value="">— Selecciona —</option>
                    @foreach ($contratosCompras as $c)
                        <option value="{{ $c['id'] }}" @selected(old('contrato_id', $corteSaved?->contrato_id) == $c['id'])>{{ $c['folio'] }} · {{ $c['cliente_nombre'] }}</option>
                    @endforeach
                </select>
                @if (empty($contratosCompras))
                    <span class="mt-1 block text-[11px] text-red-600">No se pudieron cargar los contratos de Control.</span>
                @endif
            </label>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" formaction="{{ route('admin.viajes.corte.save', $trip) }}" class="rounded-xl bg-[#2B1113]/5 px-4 py-2 text-xs font-bold text-[#2B1113] hover:bg-[#2B1113]/10">Guardar</button>
            <button type="submit" data-confirm="¿Enviar el corte y sus conceptos a Control Reportes?" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:opacity-90 transition-opacity">{{ $corteSaved?->sent_at ? 'Reenviar corte a Control' : 'Enviar corte a Control' }}</button>
        </div>
    </form>
</x-admin-layout>
