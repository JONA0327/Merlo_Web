<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Lote de folios</title>
<style>
    @page { size: letter portrait; margin: 8mm; }
    body { font-family: Helvetica, Arial, sans-serif; color: #2B1113; margin: 0; padding: 0; }
    table.sheet { width: 100%; border-collapse: separate; border-spacing: 6mm 4mm; page-break-after: always; }
    table.sheet.last { page-break-after: auto; }
    table.sheet td { width: 85.6mm; height: 54mm; padding: 0; vertical-align: top; }
    /* Tamaño tarjeta: 85.6 x 54 mm */
    .card { width: 85.6mm; height: 54mm; border: 0.3mm solid #8C1D2B; border-radius: 3mm; text-align: center; }
    .logo { height: 13mm; margin-top: 3mm; }
    .folio { font-size: 52px; font-weight: bold; letter-spacing: 2px; line-height: 17mm; margin-top: 1mm; }
    .tel { font-size: 9px; line-height: 1.35; margin-top: 1mm; }
</style>
</head>
<body>
    @foreach ($packages->chunk(8) as $page)
        <table class="sheet {{ $loop->last ? 'last' : '' }}">
            @foreach ($page->chunk(2) as $row)
                <tr>
                    @foreach ($row as $package)
                        <td><div class="card">
                            <img class="logo" src="{{ public_path('Logo.png') }}">
                            <div class="folio">{{ $package->folio_label }}</div>
                            <div class="tel"><b>WhatsApp:</b> 444 300 9923<br><b>Oficina:</b> 444 814 6688 &middot; 444 812 1651</div>
                        </div></td>
                    @endforeach
                    @if ($row->count() < 2)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
