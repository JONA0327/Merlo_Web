<x-admin-layout active="paqueteria" title="Corte del día · Paquetería">
    @php $money = fn ($v) => '$'.number_format((float) $v, 2); @endphp

    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Corte de paquetería</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Lo cobrado en el día se manda a Control Reportes como ingreso de paquetería.</p>
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
                <li class="flex justify-between py-2"><span class="text-[#2B1113]/70">{{ $p->paid_at->format('H:i') }} · {{ $unit?->client_name }} · {{ $folios }}</span><span class="font-semibold text-[#2B1113]">{{ $money($p->amount) }}</span></li>
            @empty
                <li class="py-4 text-[#2B1113]/50">No hubo cobros este día.</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ route('admin.paqueteria.corte.store') }}" class="mt-5" onsubmit="return confirm('¿Enviar el corte del {{ $date->format('d/m/Y') }} a Control Reportes?')">
            @csrf
            <input type="hidden" name="fecha" value="{{ $date->format('Y-m-d') }}">
            <button type="submit" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:opacity-90">{{ $corte?->sent_at ? 'Reenviar corte' : 'Enviar corte a Control' }}</button>
        </form>
    </div>
</x-admin-layout>
