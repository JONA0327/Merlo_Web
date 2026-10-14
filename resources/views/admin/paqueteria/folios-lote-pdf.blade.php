<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Lote de folios</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #2B1113; margin: 0; padding: 24px; }
    .header { margin-bottom: 18px; }
    .header h1 { font-size: 16px; margin: 0 0 4px 0; }
    .header p { font-size: 10px; color: #6b5c5e; margin: 0; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid td { width: 25%; text-align: center; padding: 10px; vertical-align: top; }
    .card { border: 1px solid #ddd; border-radius: 6px; padding: 14px 6px; }
    .folio { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
    .brand { font-size: 8px; color: #999; margin-top: 4px; }
</style>
</head>
<body>
    <div class="header">
        <h1>Lote de {{ $packages->count() }} folios &middot; Paquetería</h1>
        <p>Merlo Transportes &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="grid">
        <tr>
            @foreach ($packages as $i => $package)
                <td>
                    <div class="card">
                        <div class="folio">{{ $package->folio_label }}</div>
                        <div class="brand">Merlo Transportes &middot; Paqueteria</div>
                    </div>
                </td>
                @if (($i + 1) % 4 === 0 && ! $loop->last)
                    </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>
</body>
</html>
