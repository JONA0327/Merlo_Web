<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Lista de asientos</title>
@php
    $unit = $trip->busUnit;
    $deckNames = ['lower' => 'P. INFERIOR', 'upper' => 'P. SUPERIOR'];
    // Same order as the printed sheet: SUPERIOR first, INFERIOR below.
    $seatsByDeck = $seats->groupBy('deck');
    $seatsByDeck = $unit->has_upper_deck ? $seatsByDeck->sortKeysDesc() : $seatsByDeck->sortKeys();

    // Flat "printed sheet" design: no pills/checkboxes, ruled lines, colors
    // only for meaning — name color = ticket type, green background = paid.
    //
    // A4 PORTRAIT as DomPDF really renders it: about 673x954 CSS px (measured
    // from real output; a browser's 794x1123 would overflow). Per deck, each
    // printed row of 4 seats is a block of 2 lines: at its left the row's
    // seat boxes (1 2 | 3 4), then the two name columns.
    $pageW = 673;
    $pageH = 954;
    $bodyPad = 16;
    $headerH = 66;   // title, date, legend
    $mapColW = 128;  // the seat boxes of one row
    $cellWidth = ($pageW - 2 * $bodyPad - $mapColW) / 2 - 12; // px of one name cell

    // Estimated printed height of the list at a given font: a seat's line wraps
    // to 2+ lines when its names (+ pending amount) are wider than the cell.
    $estimateHeight = function (int $fs, int $pad) use ($seatsByDeck, $reservationsBySeat, $cellWidth, $unit) {
        $lineH = $fs + 2 * $pad + 3;
        $small = max(7, $fs - 2); // amount font
        $cellLines = function ($seat) use ($reservationsBySeat, $fs, $small, $cellWidth) {
            $width = 2.4 * $fs + 8; // seat number
            foreach ($reservationsBySeat->get($seat->id, collect()) as $r) {
                // name ~0.5em/char; pending amount "$1,100.00" ~9 chars of the small font
                $width += mb_strlen((string) $r->customer_display_name) * 0.5 * $fs + ($r->isPaymentCompleted() ? 0 : 9 * 0.5 * $small + 6) + 10;
            }

            return max(1, (int) ceil($width / $cellWidth));
        };
        $total = 0;
        foreach ($seatsByDeck as $deckSeats) {
            $total += $unit->has_upper_deck ? 24 : 0; // deck title
            foreach ($deckSeats->chunk(4) as $block) {
                $left = $block->slice(0, 2)->values();
                $right = $block->slice(2, 2)->values();
                for ($i = 0, $n = max($left->count(), $right->count()); $i < $n; $i++) {
                    $lines = max($left->has($i) ? $cellLines($left[$i]) : 1, $right->has($i) ? $cellLines($right[$i]) : 1);
                    $total += $lines * $lineH + ($i === $n - 1 ? 2 : 0);
                }
            }
        }

        return $total;
    };

    // Everything must fit on ONE sheet: the biggest font whose estimated
    // height fits (falls through to the smallest if nothing does).
    $budget = $pageH - 2 * $bodyPad - $headerH - 10;
    $fs = 7;
    foreach ([15, 14, 13, 12, 11, 10, 9, 8] as $candidate) {
        if ($estimateHeight($candidate, 1) <= $budget) {
            $fs = $candidate;
            break;
        }
    }
    $pad = 1;
    // Leftover height goes to row padding (air between lines) instead of an
    // unused strip at the bottom; only 80% of the estimated slack, at most 4px.
    $printedLines = $seatsByDeck->sum(fn ($d) => $d->chunk(4)->sum(fn ($b) => min($b->count(), 2)));
    $pad += max(0, min(4, (int) floor(($budget - $estimateHeight($fs, 1)) * 0.8 / max(1, 2 * $printedLines))));
    $lineH = $fs + 2 * $pad + 3;
    $small = max(7, $fs - 2);
    // Seat boxes: every column has an explicit pixel width (DomPDF otherwise
    // shares the width unevenly and the boxes come out crooked), every box
    // has the same size in every row, and there is a visible gap between seats.
    $aisleW = 16;                                              // the pasillo between the two pairs
    $gap = 6;                                                  // space between neighbouring seats (3px each side)
    $boxW = (int) floor(($mapColW - $aisleW - 4 * $gap) / 4); // one seat box
    $cellW = $boxW + $gap;
    $mapTableW = 4 * $cellW + $aisleW;
    $boxH = (int) round(2 * $lineH - 3);                       // same height for ALL boxes
@endphp
<style>
    /* The body's own padding is the margin: DomPDF's default page margin on top of it pushed a full list onto a 2nd sheet. */
    @page { margin: 0; }
    body { font-family: Helvetica, Arial, sans-serif; color: #2B1113; margin: 0; padding: {{ $bodyPad }}px; font-size: 12px; }
    .header h1 { font-size: 15px; margin: 0 0 2px 0; }
    .header p { font-size: 11px; color: #6b5c5e; margin: 0 0 2px 0; }
    .header .meta { font-size: 9px; color: #9a8a8c; margin-top: 3px; }
    .legend { margin-top: 4px; }
    .legend-item { display: inline-block; margin-right: 14px; font-size: 9px; font-weight: bold; }
    .legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
    .legend-dot.ida { background: #075985; }
    .legend-dot.regreso { background: #991b1b; }
    .legend-dot.especial { background: #065f46; }
    .legend-dot.redondo { background: #2B1113; }
    .legend-dot.planta { background: #6B21A8; }
    .legend-paid { display: inline-block; width: 14px; height: 8px; background: #c8e6c1; border: 1px solid #8fbf86; margin-right: 4px; }
    .deck-title { font-size: 12px; font-weight: bold; margin: 8px 0 2px 0; color: #2B1113; }
    .empty { padding: 18px; text-align: center; color: #9a8a8c; }

    /* Hoja plana: renglones con línea, sin etiquetas ni casillas. */
    table.manifest { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.manifest tr { page-break-inside: avoid; }
    table.manifest td { padding: {{ $pad }}px 6px; vertical-align: middle; border-bottom: 1px solid #c9bfc1; font-size: {{ $fs }}px; }
    table.manifest td.col-left, table.manifest td.col-right { width: {{ round($cellWidth + 12) }}px; }
    table.manifest td.col-left { border-right: 1px solid #8a7f81; }
    table.manifest tr.block-end td { border-bottom: 1px solid #6b5c5e; }
    table.manifest td.paid { background: #c8e6c1; }
    .seat-no { display: inline-block; width: {{ round(2.4 * $fs) }}px; font-weight: bold; }
    .name { font-weight: bold; }
    .name.ida { color: #075985; }
    .name.regreso { color: #991b1b; }
    .name.especial { color: #065f46; }
    .name.redondo { color: #2B1113; }
    .name.planta { color: #6B21A8; }
    .name-sep { font-weight: bold; color: #6b5c5e; margin: 0 2px; }
    .amount { font-size: {{ $small }}px; font-weight: bold; color: #92400e; margin-left: 3px; }
    .tag { font-size: {{ $small }}px; font-weight: bold; color: #065f46; margin-left: 3px; }

    /* Recuadros de la fila (1 2 | 3 4) a la izquierda, planos y coloreados por tipo. */
    table.manifest td.map-cell { width: {{ $mapColW }}px; padding: {{ $pad }}px 6px {{ $pad }}px 0; border-bottom: 1px solid #6b5c5e; vertical-align: middle; }
    table.mrow { width: {{ $mapTableW }}px; table-layout: fixed; border-collapse: collapse; }
    table.mrow td { width: {{ $cellW }}px; padding: 0 {{ $gap / 2 }}px; border: 0; vertical-align: middle; }
    table.mrow td.aisle { width: {{ $aisleW }}px; padding: 0; } /* the pasillo keeps its space but is not drawn */
    /* Casillas sin color (el color va en los nombres); solo borde y el número un poco más grande. */
    .mbox { border: 1px solid #2B1113; background: #ffffff; font-size: 10px; font-weight: bold; line-height: 11px; text-align: left; height: {{ $boxH }}px; }
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
        <p class="meta">Merlo Transportes &middot; Generado el {{ now()->toSpanishLongDate() }} {{ now()->format('H:i') }} &middot; {{ $seats->count() }} asiento{{ $seats->count() === 1 ? '' : 's' }} &middot; {{ $reservationsBySeat->count() }} ocupado{{ $reservationsBySeat->count() === 1 ? '' : 's' }}</p>
        <p class="legend">
            <span class="legend-item"><span class="legend-dot ida"></span>Ida</span>
            <span class="legend-item"><span class="legend-dot redondo"></span>Redondo</span>
            <span class="legend-item"><span class="legend-dot especial"></span>Especial</span>
            <span class="legend-item"><span class="legend-dot regreso"></span>Regreso</span>
            <span class="legend-item"><span class="legend-dot planta"></span>Predeterminado</span>
            <span class="legend-item"><span class="legend-paid"></span>Pagado</span>
            <span class="legend-item"><span style="color: #92400e;">$ pendiente</span></span>
        </p>
    </div>

    @if ($seats->isEmpty())
        <p class="empty">Esta unidad no tiene asientos configurados.</p>
    @else
        @php
            // Colors: "de planta" purple > any regreso red (even rescheduled) >
            // ida blue > redondo neutral; an especial takes the color of what it
            // really is (see SeatReservation::colorKey()). A planta group is
            // flagged on its root's notes only, hence the lookup by root id.
            $plantaRootIds = $reservationsBySeat->flatten(1)
                ->filter(fn ($r) => str_starts_with((string) $r->notes, 'Asiento de planta'))
                ->map(fn ($r) => $r->groupRootId())
                ->all();
            $typeClass = fn ($r) => in_array($r->groupRootId(), $plantaRootIds, true) ? 'planta' : $r->colorKey();
            $mapTypeClass = function ($seatId) use ($reservationsBySeat, $typeClass) {
                $r = $reservationsBySeat->get($seatId, collect())->first();

                return $r ? $typeClass($r) : '';
            };
            // "Especial" seats are sold as a mancuerna (2+ seats, ONE charge) —
            // only they get the ESPECIAL tag, and the amount prints once per
            // group (on its first seat) so the pair isn't charged as two.
            $especialGroups = $reservationsBySeat->flatten(1)
                ->filter(fn ($r) => $r->trip_type === \App\Models\TripTicketPrice::TYPE_ESPECIAL)
                ->groupBy(fn ($r) => $r->groupRootId())
                ->map(fn ($g) => $g->sortBy(fn ($r) => $r->seat?->label, SORT_NATURAL)->values());
            $especialTag = function ($r) use ($especialGroups) {
                $group = $especialGroups->get($r->groupRootId());
                if (! $group) {
                    return null;
                }
                $isFirst = $group->first()->id === $r->id;
                $labels = $group->map(fn ($x) => $x->seat?->label)->filter()->implode(' + ');

                $mode = match ($group->first()->especialMode()) {
                    \App\Models\SeatReservation::ESPECIAL_ONE_WAY => 'IDA ',
                    \App\Models\SeatReservation::ESPECIAL_RETURN => 'REGRESO ',
                    default => '',
                };

                return ['first' => $isFirst, 'labels' => $mode.$labels, 'total' => $group->sum(fn ($x) => (float) $x->unit_price), 'abono' => $group->sum(fn ($x) => (float) $x->abono)];
            };
        @endphp

        @foreach ($seatsByDeck as $deck => $deckSeats)
            @if ($unit->has_upper_deck)
                <p class="deck-title">{{ $deckNames[$deck] ?? $deck }} &nbsp; UNIDAD {{ $unit->name }}</p>
            @endif

            <table class="manifest">
                <tbody>
                    {{-- Every 4 consecutive seats = one printed row: the first 2
                         stack in the left column, the next 2 in the right one
                         (1/2 left, 3/4 right; 5/6 left, 7/8 right; ...). --}}
                    @foreach ($deckSeats->chunk(4) as $block)
                        @php
                            $sides = [$block->slice(0, 2)->values(), $block->slice(2, 2)->values()];
                            $lineCount = max($sides[0]->count(), $sides[1]->count());
                        @endphp
                        @for ($line = 0; $line < $lineCount; $line++)
                            <tr class="{{ $line === $lineCount - 1 ? 'block-end' : '' }}">
                                @if ($line === 0)
                                    <td class="map-cell" rowspan="{{ $lineCount }}">
                                        {{-- Left pair | pasillo | right pair: fixed columns, so the
                                             aisle and the boxes line up in every row. --}}
                                        <table class="mrow"><tr>
                                            @foreach ([0, 1] as $i)
                                                <td>@if ($sides[0]->has($i))<div class="mbox {{ $mapTypeClass($sides[0][$i]->id) }}">{{ $sides[0][$i]->label }}</div>@endif</td>
                                            @endforeach
                                            <td class="aisle"></td>
                                            @foreach ([0, 1] as $i)
                                                <td>@if ($sides[1]->has($i))<div class="mbox {{ $mapTypeClass($sides[1][$i]->id) }}">{{ $sides[1][$i]->label }}</div>@endif</td>
                                            @endforeach
                                        </tr></table>
                                    </td>
                                @endif
                                @foreach ($sides as $sideIndex => $side)
                                    @php
                                        $seat = $side->get($line);
                                        $seatReservations = $seat ? $reservationsBySeat->get($seat->id, collect()) : collect();
                                        $allPaid = $seatReservations->isNotEmpty() && $seatReservations->every(fn ($r) => $r->isPaymentCompleted());
                                    @endphp
                                    <td class="{{ $sideIndex === 0 ? 'col-left' : 'col-right' }} {{ $allPaid ? 'paid' : '' }}">
                                        @if ($seat)
                                            <span class="seat-no">{{ $seat->label }}</span>
                                            @foreach ($seatReservations as $r)
                                                @if (! $loop->first)<span class="name-sep">/</span>@endif
                                                <span class="name {{ $typeClass($r) }}">{{ $r->customer_display_name }}</span>
                                                @php $esp = $especialTag($r); @endphp
                                                @if ($esp)
                                                    <span class="tag">ESPECIAL {{ $esp['labels'] }}</span>
                                                @endif
                                                @if (! $r->isPaymentCompleted() && (! $esp || $esp['first']))
                                                    <span class="amount">${{ number_format(max(0, ($esp ? $esp['total'] - $esp['abono'] : (float) $r->unit_price - (float) $r->abono)), 2) }}</span>
                                                @endif
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endfor
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</body>
</html>
