<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Lista de asientos</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #2B1113; margin: 0; padding: 24px; font-size: 12px; }
    .header h1 { font-size: 18px; margin: 0 0 4px 0; }
    .header p { font-size: 11px; color: #6b5c5e; margin: 0 0 2px 0; }
    .header .meta { font-size: 9px; color: #9a8a8c; margin-top: 8px; }
    table.manifest { width: 100%; border-collapse: collapse; margin-top: 16px; }
    table.manifest td { padding: 6px 8px; vertical-align: middle; width: 50%; }
    table.manifest td.col-left { border-right: 1px solid #c9bfc1; }
    table.manifest tr.block-end td { border-bottom: 1px solid #c9bfc1; padding-bottom: 12px; }
    table.manifest tr.block-shaded td { background: #FFFBF6; }
    .seat-badge { display: inline-block; border: 1px solid #8C1D2B; border-radius: 4px; padding: 2px 7px; font-weight: bold; font-size: 13px; margin-right: 6px; }
    .name { font-weight: bold; font-size: 13px; }
    .check-col { width: 18px; }
    .box { display: inline-block; width: 13px; height: 13px; border: 1px solid #2B1113; }
    .empty { padding: 18px; text-align: center; color: #9a8a8c; }
</style>
</head>
<body>
    <div class="header">
        {{-- DomPDF's default Helvetica font has no glyph for "→" (renders
             as "?"), same issue already solved in TicketImageService —
             use a plain ASCII arrow instead. --}}
        <h1>Lista de asientos &middot; {{ $trip->from }} -&gt; {{ $trip->to }}</h1>
        <p>{{ $trip->day ? $trip->day->toSpanishLongDate() : 'Sin fecha' }} &middot; {{ $trip->departure_time_formatted ?? 'Sin horario' }} &middot; {{ $trip->busUnit->name ?? 'Sin unidad' }}</p>
        @if ($trip->return_date)
            <p>Regreso: {{ $trip->return_date->toSpanishLongDate() }}</p>
        @endif
        <p class="meta">Merlo Transportes &middot; Generado el {{ now()->toSpanishLongDate() }} {{ now()->format('H:i') }} &middot; {{ $reservations->count() }} asiento{{ $reservations->count() === 1 ? '' : 's' }}</p>
    </div>

    @if ($reservations->isEmpty())
        <p class="empty">Aún no hay asientos apartados ni comprados para este viaje.</p>
    @else
        @php
            // Matches the bus's real seat distribution: every 4 consecutive
            // seats form one printed block — the first 2 stack in the left
            // column, the next 2 stack in the right column (1/2 left,
            // 3/4 right; 5/6 left, 7/8 right; ...), with a divider line
            // between blocks. $reservations already arrives sorted by
            // seat label.
            $blocks = $reservations->chunk(4);
        @endphp

        <table class="manifest">
            <tbody>
                @foreach ($blocks as $blockIndex => $block)
                    @php
                        $left = $block->slice(0, 2)->values();
                        $right = $block->slice(2, 2)->values();
                        $lineCount = max($left->count(), $right->count());
                        $shaded = $blockIndex % 2 === 1;
                    @endphp
                    @for ($line = 0; $line < $lineCount; $line++)
                        <tr class="{{ $shaded ? 'block-shaded' : '' }} {{ $line === $lineCount - 1 ? 'block-end' : '' }}">
                            <td class="col-left">
                                @if ($left->has($line))
                                    <span class="check-col"><span class="box"></span></span>
                                    <span class="seat-badge">{{ $left[$line]->seat?->label ?? '—' }}</span>
                                    <span class="name">{{ $left[$line]->customer_display_name }}</span>
                                @endif
                            </td>
                            <td class="col-right">
                                @if ($right->has($line))
                                    <span class="check-col"><span class="box"></span></span>
                                    <span class="seat-badge">{{ $right[$line]->seat?->label ?? '—' }}</span>
                                    <span class="name">{{ $right[$line]->customer_display_name }}</span>
                                @endif
                            </td>
                        </tr>
                    @endfor
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
