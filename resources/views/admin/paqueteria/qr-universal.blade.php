<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QR universal · Paquetería</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            #qr-wrap { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; min-height: calc(100vh - 73px); padding: 10px; }
            #qr-code { width: min(96vw, 82vh); height: min(96vw, 82vh); }
            #qr-code svg { width: 100%; height: 100%; }
            @media print {
                @page { margin: 0; }
                body { margin: 0; }
                #qr-wrap { min-height: 100vh; padding: 0; gap: 6mm; }
                #qr-code { width: min(98vw, 86vh); height: min(98vw, 86vh); }
            }
        </style>
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-black/5 px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
            <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="descargarQR()" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                    Descargar
                </button>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                    Imprimir
                </button>
            </div>
        </div>

        <div id="qr-wrap">
            <div id="qr-code">{!! $svg !!}</div>
            <p class="font-[Poppins] text-center text-xl font-extrabold tracking-wide text-[#2B1113] print:text-3xl">
                ESCANEA PARA REGISTRAR EL PAQUETE Y ASIGNAR FOLIO
            </p>
            <p class="text-center text-xs text-[#2B1113]/60 print:text-base">
                Si no puedes escanear, entra directo a:
                <a href="{{ $url }}" class="font-semibold text-[#8C1D2B]">{{ $url }}</a>
            </p>
        </div>

        <script>
            function descargarQR() {
                const svg = document.querySelector('#qr-wrap svg');
                const blob = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'qr-paqueteria.svg';
                a.click();
                URL.revokeObjectURL(url);
            }
        </script>
    </body>
</html>
