<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Corte {{ $date->format('d/m/Y') }} · Paquetería</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @page { size: letter portrait; margin: 12mm; }
            @media print { html, body { background: #fff !important; } table { break-inside: auto; } tr { break-inside: avoid; } }
        </style>
    </head>
    @php
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $folioOf = fn ($u) => $u instanceof \App\Models\PackageGroup
            ? $u->packages->sortBy('folio_number')->pluck('folio_label')->filter()->implode(', ')
            : $u->folio_label;
        $nameOf = fn ($u) => $u instanceof \App\Models\PackageGroup ? $u->client_name : $u->displayClientName();
        $phoneOf = fn ($u) => $u instanceof \App\Models\PackageGroup ? $u->phone : $u->displayPhone();
        $fmtPhone = function ($p) {
            $d = preg_replace('/\D/', '', (string) $p);
            return strlen($d) === 10 ? substr($d, 0, 3).' '.substr($d, 3, 3).' '.substr($d, 6) : ($p ?: '—');
        };
    @endphp
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <div class="print:hidden border-b border-black/5 bg-white px-6 py-4 flex items-center justify-between">
            <a href="{{ route('admin.paqueteria.corte', ['fecha' => $date->format('Y-m-d')]) }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver al corte</a>
            <button type="button" onclick="window.print()" class="rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white">Imprimir</button>
        </div>

        <div class="mx-auto max-w-4xl p-6 print:p-0">
            <h1 class="text-xl font-bold">Merlo Transportes · Corte de paquetería</h1>
            <p class="text-sm text-[#2B1113]/60">{{ $date->format('d/m/Y') }}</p>

            <h2 class="mt-6 text-base font-bold">Pagaron ({{ $payments->count() }}) · {{ $money($payments->sum('amount')) }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead><tr class="border-b-2 border-black/20 text-left text-xs uppercase"><th class="py-1 pr-2">Hora</th><th class="pr-2">Cliente</th><th class="pr-2">Folio</th><th class="pr-2">Teléfono</th><th class="text-right">Pagó</th></tr></thead>
                <tbody>
                    @forelse ($payments as $p)
                        @php $u = $p->payable; @endphp
                        <tr class="border-b border-black/10">
                            <td class="py-1 pr-2">{{ $p->paid_at->format('H:i') }}</td>
                            <td class="pr-2">{{ $u ? $nameOf($u) : '—' }}</td>
                            <td class="pr-2">{{ ($u ? $folioOf($u) : null) ?: 'Sin folio' }}</td>
                            <td class="pr-2">{{ $u ? $fmtPhone($phoneOf($u)) : '—' }}</td>
                            <td class="text-right font-semibold">{{ $money($p->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-[#2B1113]/50">No hubo cobros este día.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2 class="mt-8 text-base font-bold">Egresos ({{ $expenses->count() }}) · {{ $money($expenses->sum('amount')) }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead><tr class="border-b-2 border-black/20 text-left text-xs uppercase"><th class="py-1 pr-2">Concepto</th><th class="text-right">Cantidad</th></tr></thead>
                <tbody>
                    @forelse ($expenses as $e)
                        <tr class="border-b border-black/10"><td class="py-1 pr-2">{{ $e->concept }}</td><td class="text-right font-semibold">{{ $money($e->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-3 text-[#2B1113]/50">Sin egresos.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-2 text-right text-sm font-bold">Queda: {{ $money($payments->sum('amount') - $expenses->sum('amount')) }}</p>

            <h2 class="mt-8 text-base font-bold">Pendientes ({{ $pending->count() }}) · {{ $money($pending->sum(fn ($u) => $u->pendingAmount())) }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead><tr class="border-b-2 border-black/20 text-left text-xs uppercase"><th class="py-1 pr-2">Cliente</th><th class="pr-2">Folio</th><th class="pr-2">Teléfono</th><th class="pr-2">Entregado</th><th class="text-right">Debe</th></tr></thead>
                <tbody>
                    @forelse ($pending as $u)
                        <tr class="border-b border-black/10">
                            <td class="py-1 pr-2">{{ $nameOf($u) }}</td>
                            <td class="pr-2">{{ $folioOf($u) ?: 'Sin folio' }}</td>
                            <td class="pr-2">{{ $fmtPhone($phoneOf($u)) }}</td>
                            <td class="pr-2">{{ ($u instanceof \App\Models\PackageGroup ? $u->packages->max('delivered_at') : $u->delivered_at)?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-right font-semibold">{{ $money($u->pendingAmount()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-[#2B1113]/50">Sin pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </body>
</html>
