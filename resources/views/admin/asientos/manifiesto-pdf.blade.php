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
    // only for meaning — name color = ticket type, green highlight = especial, green dot = paid.
    //
    // A4 PORTRAIT in DomPDF's CSS px (96 dpi: 1 px = 0.75 pt, so 1 cm = 37.8 px):
    // 794 x 1122. Measured on the real output: a box declared 46px tall came out
    // 33.8pt = 46 x 0.735, so these are the true units. Per deck, each printed row
    // of 4 seats is a block of 2 lines: at its left the row's seat boxes (1 2 | 3 4),
    // then the two name columns.
    $pageW = 794;
    $pageH = 1122;
    $bodyPad = 16;
    $headerH = 66;   // title, date, legend
    $contentW = $pageW - 2 * $bodyPad - 2;                 // px of usable width
    $budget = $pageH - 2 * $bodyPad - $headerH - 20;       // px for the decks (20px of safety so it never spills to a 2nd sheet)

    // Seat boxes are SQUARES of 1.5 cm a side (56.7 px), the same in every
    // row. If the unit has so many rows that 1.5 cm would not fit on ONE sheet,
    // they shrink just enough to fit. Every column has an explicit pixel width
    // (DomPDF otherwise shares it unevenly and the boxes come out crooked).
    $aisleW = 16;   // the pasillo between the two pairs
    $gap = 6;       // space between neighbouring seats (3px each side)
    $blockCount = $seatsByDeck->sum(fn ($d) => (int) ceil($d->count() / 4));
    $titlesH = $unit->has_upper_deck ? 24 * $seatsByDeck->count() : 0;
    $boxS = (int) max(24, min(56, floor(($budget - $titlesH) / max(1, $blockCount)) - 6));
    // CSS cell widths EXCLUDE their padding, so every column below is declared as
    // content width and the paddings are added up in the totals: map cell =
    // table + 6px right padding, name cells = content + 12px padding each.
    $cellW = $boxS + $gap;                                   // one seat column, padding included
    $mapTableW = 4 * $cellW + $aisleW;
    $cellWidth = ($contentW - $mapTableW - 6) / 2 - 12;      // px of one name cell (content)
    $boxBlockH = $boxS + 6; // a block is at least as tall as its box (+ padding, rule, row rounding)

    // Estimated printed height of the decks at a given font: a seat's line wraps
    // to 2+ lines when its names are wider than the cell, and a block is never
    // shorter than its seat boxes.
    $estimateHeight = function (int $fs) use ($seatsByDeck, $reservationsBySeat, $cellWidth, $unit, $boxBlockH) {
        $lineH = $fs + 2 + 3;
        $small = max(7, $fs - 2); // tag font
        $cellLines = function ($seat) use ($reservationsBySeat, $fs, $small, $cellWidth) {
            $width = 2.4 * $fs + 8; // seat number
            foreach ($reservationsBySeat->get($seat->id, collect()) as $r) {
                // name ~0.5em/char, paid dot ~10px, "ESPECIAL 35 + 36" tag ~22 chars of the small font
                $width += mb_strlen((string) $r->customer_display_name) * 0.5 * $fs
                    + ($r->isPaymentCompleted() ? 10 : 0)
                    + ($r->isEspecial() ? 22 * 0.55 * $small + 6 : 0)
                    + 10;
            }

            return max(1, (int) ceil($width / $cellWidth));
        };
        $total = 0;
        foreach ($seatsByDeck as $deckSeats) {
            $total += $unit->has_upper_deck ? 24 : 0; // deck title
            foreach ($deckSeats->chunk(4) as $block) {
                $left = $block->slice(0, 2)->values();
                $right = $block->slice(2, 2)->values();
                $names = 1;
                for ($i = 0, $n = max($left->count(), $right->count()); $i < $n; $i++) {
                    $names += max($left->has($i) ? $cellLines($left[$i]) : 1, $right->has($i) ? $cellLines($right[$i]) : 1) * $lineH;
                }
                $total += max($names, $boxBlockH);
            }
        }

        return $total;
    };

    // Everything must fit on ONE sheet: the biggest font whose estimated
    // height fits (falls through to the smallest if nothing does).
    $fs = 7;
    foreach ([15, 14, 13, 12, 11, 10, 9, 8] as $candidate) {
        if ($estimateHeight($candidate) <= $budget) {
            $fs = $candidate;
            break;
        }
    }
    $pad = 1;
    $small = max(7, $fs - 2);

    // Seats per type for the legend (planta > especial > ida/regreso/redondo).
    $allRes = $reservationsBySeat->flatten(1);
    $plantaIds = $allRes->filter(fn ($r) => str_starts_with((string) $r->notes, 'Asiento de planta'))->map(fn ($r) => $r->groupRootId())->all();
    $typeCounts = $allRes->countBy(fn ($r) => in_array($r->groupRootId(), $plantaIds, true) ? 'planta' : ($r->isEspecial() ? 'especial' : $r->colorKey()));
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
    .legend-esp { display: inline-block; width: 14px; height: 8px; background: #c8e6c1; border: 1px solid #8fbf86; margin-right: 4px; }
    .legend-dot.paid { background: #16a34a; }
    .deck-title { font-size: 12px; font-weight: bold; margin: 8px 0 2px 0; color: #2B1113; }
    .empty { padding: 18px; text-align: center; color: #9a8a8c; }

    /* Hoja plana: renglones con línea, sin etiquetas ni casillas. */
    table.manifest { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.manifest tr { page-break-inside: avoid; }
    table.manifest td { padding: {{ $pad }}px 6px; vertical-align: middle; border-bottom: 1px solid #c9bfc1; font-size: {{ $fs }}px; }
    table.manifest td.col-left, table.manifest td.col-right { width: {{ round($cellWidth) }}px; }
    table.manifest td.col-left { border-right: 1px solid #8a7f81; }
    table.manifest tr.block-end td { border-bottom: 1px solid #6b5c5e; }
    table.manifest td.esp { background: #c8e6c1; }
    .paid-dot { display: inline-block; width: {{ max(6, (int) round($fs * 0.7)) }}px; height: {{ max(6, (int) round($fs * 0.7)) }}px; border-radius: 50%; background: #16a34a; margin-left: 4px; }
    .seat-no { display: inline-block; width: {{ round(2.4 * $fs) }}px; font-weight: bold; }
    .name { font-weight: bold; }
    .name.ida { color: #075985; }
    .name.regreso { color: #991b1b; }
    .name.especial { color: #065f46; }
    .name.redondo { color: #2B1113; }
    .name.planta { color: #6B21A8; }
    .name-sep { font-weight: bold; color: #6b5c5e; margin: 0 2px; }
    .tag { font-size: {{ $small }}px; font-weight: bold; color: #065f46; margin-left: 3px; }

    /* Recuadros de la fila (1 2 | 3 4) a la izquierda, planos y coloreados por tipo. */
    table.manifest td.map-cell { width: {{ $mapTableW }}px; padding: {{ $pad }}px 6px {{ $pad }}px 0; border-bottom: 1px solid #6b5c5e; vertical-align: middle; }
    /* Casillas: bloques en línea de tamaño fijo (la tabla interna repartía el ancho en columnas iguales e ignoraba los anchos). */
    .mrow { white-space: nowrap; }
    .aisle { display: inline-block; width: {{ $aisleW }}px; height: 1px; vertical-align: top; } /* the pasillo keeps its space but is not drawn */
    /* Casillas cuadradas de ~1.5 cm, sin color (el color va en los nombres); solo borde y el número. */
    .mbox { display: inline-block; vertical-align: top; box-sizing: content-box; width: {{ $boxS - 2 }}px; height: {{ $boxS - 2 }}px; margin: 0 {{ $gap / 2 }}px; border: 1px solid #2B1113; background: #ffffff; font-size: 12px; font-weight: bold; line-height: 13px; text-align: left; }
    .mbox.ghost { visibility: hidden; }
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
            <span class="legend-item"><span class="legend-dot ida"></span>Ida ({{ $typeCounts->get('ida', 0) }})</span>
            <span class="legend-item"><span class="legend-dot redondo"></span>Redondo ({{ $typeCounts->get('redondo', 0) }})</span>
            <span class="legend-item"><span class="legend-dot especial"></span>Especial ({{ $typeCounts->get('especial', 0) }})</span>
            <span class="legend-item"><span class="legend-dot regreso"></span>Regreso ({{ $typeCounts->get('regreso', 0) }})</span>
            <span class="legend-item"><span class="legend-dot planta"></span>Predeterminado ({{ $typeCounts->get('planta', 0) }})</span>
            <span class="legend-item"><span class="legend-esp"></span>Especial (resaltado)</span>
            <span class="legend-item"><span class="legend-dot paid"></span>Pagado</span>
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
            // only they get the ESPECIAL tag, naming the seats of the pair.
            $especialGroups = $reservationsBySeat->flatten(1)
                ->filter(fn ($r) => $r->trip_type === \App\Models\TripTicketPrice::TYPE_ESPECIAL)
                ->groupBy(fn ($r) => $r->groupRootId())
                ->map(fn ($g) => $g->sortBy(fn ($r) => $r->seat?->label, SORT_NATURAL)->values());
            $especialTag = function ($r) use ($especialGroups) {
                $group = $especialGroups->get($r->groupRootId());
                if (! $group) {
                    return null;
                }
                $labels = $group->map(fn ($x) => $x->seat?->label)->filter()->implode(' + ');

                $mode = match ($group->first()->especialMode()) {
                    \App\Models\SeatReservation::ESPECIAL_ONE_WAY => 'IDA ',
                    \App\Models\SeatReservation::ESPECIAL_RETURN => 'REGRESO ',
                    default => '',
                };

                return ['labels' => $mode.$labels];
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
                                        <div class="mrow">@foreach ([0, 1] as $i)@if ($sides[0]->has($i))<div class="mbox {{ $mapTypeClass($sides[0][$i]->id) }}">{{ $sides[0][$i]->label }}</div>@else<div class="mbox ghost"></div>@endif{{ '' }}@endforeach<div class="aisle"></div>@foreach ([0, 1] as $i)@if ($sides[1]->has($i))<div class="mbox {{ $mapTypeClass($sides[1][$i]->id) }}">{{ $sides[1][$i]->label }}</div>@else<div class="mbox ghost"></div>@endif{{ '' }}@endforeach</div>
                                    </td>
                                @endif
                                @foreach ($sides as $sideIndex => $side)
                                    @php
                                        $seat = $side->get($line);
                                        $seatReservations = $seat ? $reservationsBySeat->get($seat->id, collect()) : collect();
                                        $hasEspecial = $seatReservations->contains(fn ($r) => $r->isEspecial());
                                    @endphp
                                    <td class="{{ $sideIndex === 0 ? 'col-left' : 'col-right' }} {{ $hasEspecial ? 'esp' : '' }}">
                                        @if ($seat)
                                            <span class="seat-no">{{ $seat->label }}</span>
                                            @foreach ($seatReservations as $r)
                                                @if (! $loop->first)<span class="name-sep">/</span>@endif
                                                <span class="name {{ $typeClass($r) }}">{{ $r->customer_display_name }}</span>@if ($r->isPaymentCompleted())<span class="paid-dot"></span>@endif
                                                @php $esp = $especialTag($r); @endphp
                                                @if ($esp)
                                                    <span class="tag">ESPECIAL {{ $esp['labels'] }}</span>
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
