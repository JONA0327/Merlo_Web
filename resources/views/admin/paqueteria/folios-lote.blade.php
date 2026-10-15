<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Lote de folios · Paquetería</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @page { size: letter portrait; margin: 8mm; }
            .page { width: 184mm; margin: 0 auto; padding: 24px 0; }
            .row { display: flex; flex-wrap: nowrap; gap: 6mm; height: 54mm; margin-bottom: 4mm; }
            .card { flex: 0 0 85.6mm; width: 85.6mm; height: 54mm; box-sizing: border-box; border: 0.3mm solid #8C1D2B; border-radius: 3mm; overflow: hidden; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: space-between; padding: 3mm 3mm 2.5mm; text-align: center; }
            .card .logo { height: 13mm; width: auto; }
            .card .tel { font-size: 7.5pt; line-height: 1.3; color: #2B1113; }
            .card .tel b { font-weight: 700; }
            .card .folio { font-family: ui-monospace, monospace; font-size: 44pt; font-weight: 900; letter-spacing: .05em; white-space: nowrap; }
            @media print {
                html, body { margin: 0 !important; padding: 0 !important; background: #fff; }
                .page { padding: 0; margin: 0; height: 240mm; overflow: hidden; break-after: page; page-break-after: always; }
                .page:last-child { break-after: auto; page-break-after: auto; }
            }
        </style>
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-black/5 px-4 sm:px-6 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold text-[#2B1113]">{{ $packages->count() }} folios generados</p>
                <p class="text-xs text-[#2B1113]/50">Imprime esta hoja y pega cada folio en su paquete correspondiente. El PDF ya quedó guardado en "Lotes generados". Estos folios no vencen.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a href="{{ route('admin.paqueteria.qr.batches') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">Lotes generados</a>
                <a href="{{ route('admin.paqueteria') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">Volver</a>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                    Imprimir
                </button>
            </div>
        </div>

        @foreach ($packages->chunk(8) as $page)
            <div class="page">
                @foreach ($page->chunk(2) as $row)
                    <div class="row">
                        @foreach ($row as $package)
                            <div class="card">
                                <img class="logo" src="{{ asset('Logo.png') }}" alt="Merlo Transportes">
                                <div class="folio">{{ $package->folio_label }}</div>
                                <div class="tel">
                                    <div><b>WhatsApp:</b> 444 300 9923</div>
                                    <div><b>Oficina:</b> 444 814 6688 &middot; 444 812 1651</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </body>
</html>
