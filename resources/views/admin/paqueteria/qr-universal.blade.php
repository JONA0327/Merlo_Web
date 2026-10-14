<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QR universal · Paquetería</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @media print {
                @page { margin: 0; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <div class="print:hidden sticky top-0 z-10 bg-white border-b border-black/5 px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
            <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                Imprimir
            </button>
        </div>

        <div class="flex min-h-[calc(100vh-73px)] print:min-h-screen items-center justify-center p-6 print:p-0">
            <div class="h-[80vw] w-[80vw] max-h-[90vh] max-w-[90vh] print:h-screen print:w-screen [&>svg]:h-full [&>svg]:w-full">{!! $svg !!}</div>
        </div>
    </body>
</html>
