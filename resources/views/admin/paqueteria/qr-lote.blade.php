<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Lote de QR · Paquetería</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @media print {
                @page { margin: 12mm; }
            }
        </style>
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-black/5 px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-[#2B1113]">{{ $packages->count() }} etiquetas generadas</p>
                <p class="text-xs text-[#2B1113]/50">Imprime esta hoja y pega cada QR en su paquete correspondiente. El PDF ya quedó guardado en "Lotes generados".</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.paqueteria.qr.batches') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">Lotes generados</a>
                <a href="{{ route('admin.paqueteria') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">Volver</a>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                    Imprimir
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 p-6 print:grid-cols-3 print:gap-4 print:p-0">
            @foreach ($packages as $package)
                <div class="flex flex-col items-center rounded-2xl border border-black/10 bg-white p-4 text-center break-inside-avoid">
                    <div class="h-[160px] w-[160px] [&>svg]:h-full [&>svg]:w-full">{!! $svgs[$package->id] !!}</div>
                    <p class="mt-2 font-mono text-xs font-bold tracking-wider text-[#2B1113]">{{ $package->tracking_code }}</p>
                    <p class="text-[10px] text-[#2B1113]/40">Merlo Transportes · Paquetería</p>
                </div>
            @endforeach
        </div>
    </body>
</html>
