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

    // A4 PORTRAIT as DomPDF really renders it: about 673x954 CSS px (measured
    // from real output; a browser's 794x1123 would overflow).
    // Sheet layout, per deck: each printed row of 4 seats = a block of 2 lines;
    // at its left the row's seat boxes (1 2 | 3 4), then the two name columns.
    $pageW = 673;
    $pageH = 954;
    $bodyPad = 16;
    $headerH = 66;   // title, date, legend
    $mapColW = 118;  // the seat boxes of one row
    $cellWidth = ($pageW - 2 * $bodyPad - $mapColW) / 2 - 16; // px of one name cell

    // Estimated printed height of the list at a given font: a seat's line wraps
    // to 2+ lines when its names + payment badges are wider than the cell.
    $estimateHeight = function (int $fs, int $pad) use ($seatsByDeck, $reservationsBySeat, $cellWidth, $unit) {
        $lineH = $fs + 2 * $pad + 3.5; // measured: ~14px per line at fs 9, pad 1
        $badgeFont = max(7, $fs - 2); // same rule as .pay-badge
        $cellLines = function ($seat) use ($reservationsBySeat, $fs, $badgeFont, $cellWidth) {
            $width = 5.0 * $fs; // checkbox + seat badge
            foreach ($reservationsBySeat->get($seat->id, collect()) as $r) {
                // measured from real output: name ~0.5em/char; badge = 20px of padding/margin
                // + text (~8.9 x its font for "PENDIENTE $1,100.00", ~3.5 x for "PAGADO")
                $width += mb_strlen((string) $r->customer_display_name) * 0.5 * $fs
                    + ($r->isPaymentCompleted() ? 14 + 3.5 * $badgeFont : 20 + 8.9 * $badgeFont) + 8;
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
                    $total += $lines * $lineH + ($i === $n - 1 ? $pad + 1.5 : 0);
                }
            }
        }

        return $total;
    };

    // Everything must fit on ONE sheet: the biggest font whose estimated
    // height fits (falls through to the smallest if nothing does).
    $padFor = fn (int $f) => $f >= 13 ? 3 : ($f >= 11 ? 2 : 1); // vertical padding grows with the font
    $fs = 7;
    foreach ([15, 14, 13, 12, 11, 10, 9, 8] as $candidate) {
        if ($estimateHeight($candidate, $padFor($candidate)) <= $pageH - 2 * $bodyPad - $headerH - 10) {
            $fs = $candidate;
            break;
        }
    }
    $tight = $fs <= 10;
    $pad = $padFor($fs);
    // Leftover height goes to row padding (more air between lines) instead of
    // an unused strip at the bottom of the sheet; only 80% of the estimated
    // slack, and at most 4px per side, to stay safely on one page.
    $printedLines = $seatsByDeck->sum(fn ($d) => $d->chunk(4)->sum(fn ($b) => min($b->count(), 2)));
    $slack = $pageH - 2 * $bodyPad - $headerH - 10 - $estimateHeight($fs, $pad);
    $pad += max(0, min(4, (int) floor($slack * 0.8 / max(1, 2 * $printedLines))));
    $lineH = $fs + 2 * $pad + 3.5;
    $boxW = max(20, (int) round(($mapColW - 14) / 4)); // one seat box
@endphp
<style>
    /* The body's own padding is the margin: DomPDF's default page margin on top of it pushed a full list onto a 2nd sheet. */
    @page { margin: 0; }
    body { font-family: Helvetica, Arial, sans-serif; color: #2B1113; margin: 0; padding: {{ $bodyPad }}px; font-size: 12px; }
    .header h1 { font-size: {{ $tight ? 15 : 18 }}px; margin: 0 0 2px 0; }
    .header p { font-size: 11px; color: #6b5c5e; margin: 0 0 2px 0; }
    .header .meta { font-size: 9px; color: #9a8a8c; margin-top: {{ $tight ? 3 : 8 }}px; }
    table.manifest { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.manifest td { padding: {{ $pad }}px 8px; vertical-align: middle; }
    table.manifest td.col-left, table.manifest td.col-right { width: {{ round($cellWidth + 16) }}px; }
    table.manifest td.col-left { border-right: 1px solid #c9bfc1; }
    table.manifest tr.block-end td { border-bottom: 1px solid #c9bfc1; padding-bottom: {{ $pad + 2 }}px; }
    table.manifest tr.block-shaded td { background: #FFFBF6; }
    .seat-badge { display: inline-block; border: 1px solid #8C1D2B; border-radius: 4px; padding: {{ $tight ? 0 : 1 }}px 6px; font-weight: bold; font-size: {{ $fs }}px; margin-right: 6px; width: 22px; text-align: center; }
    .name { font-weight: bold; font-size: {{ $fs }}px; }
    .name.ida { color: #075985; }
    .name.regreso { color: #991b1b; }
    .name.especial { color: #065f46; }
    .name.redondo { color: #2B1113; }
    .name-sep { font-weight: bold; font-size: {{ $fs }}px; color: #9a8a8c; margin: 0 3px; }
    .check-col { width: 18px; }
    .box { display: inline-block; width: {{ $tight ? 10 : 13 }}px; height: {{ $tight ? 10 : 13 }}px; border: 1px solid #2B1113; }
    /* A cada fila de 4 asientos: sus recuadros (1 2 | 3 4) a la izquierda y los nombres a la derecha. */
    table.manifest td.map-cell { width: {{ $mapColW }}px; padding: {{ $pad }}px 8px {{ $pad }}px 0; white-space: nowrap; border-bottom: 1px solid #c9bfc1; }
    .mbox { display: inline-block; width: {{ $boxW }}px; border: 1px solid #2B1113; background: #ffffff; font-size: 7px; font-weight: bold; line-height: 8px; text-align: left; vertical-align: middle; margin-right: 1px; }
    .mbox.gap { margin-left: 8px; }
    .mbox.ida { background: #bae6fd; border-color: #075985; }
    .mbox.regreso { background: #fecaca; border-color: #991b1b; }
    .mbox.especial { background: #a7f3d0; border-color: #065f46; }
    .mbox.redondo { background: #e5d5d7; border-color: #2B1113; }
    table.manifest tr { page-break-inside: avoid; }
    .deck-title { font-size: 12px; font-weight: bold; margin: 10px 0 2px 0; color: #2B1113; }
    .legend { margin-top: {{ $tight ? 3 : 8 }}px; }
    .empty { padding: 18px; text-align: center; color: #9a8a8c; }
    .pay-badge { display: inline-block; border-radius: 10px; padding: {{ $tight ? 0 : 1 }}px 8px; font-size: {{ max(7, $fs - 2) }}px; font-weight: bold; margin-left: 4px; }
    .pay-badge.paid { background: #d1fae5; color: #065f46; }
    .pay-badge.pending { background: #fef3c7; color: #92400e; }
    .pay-badge.especial { background: #d1fae5; color: #065f46; border: 1px solid #065f46; }
    .legend-item { display: inline-block; margin-right: 14px; font-size: 9px; font-weight: bold; }
    .legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
    .legend-dot.ida { background: #075985; }
    .legend-dot.regreso { background: #991b1b; }
    .legend-dot.especial { background: #065f46; }
    .legend-dot.redondo { background: #2B1113; }
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
        </p>
    </div>

    @if ($seats->isEmpty())
        <p class="empty">Esta unidad no tiene asientos configurados.</p>
    @else
        @php
            $mapTypeClass = function ($seatId) use ($reservationsBySeat) {
                $r = $reservationsBySeat->get($seatId, collect())->first();
                if (! $r) {
                    return '';
                }

                return match ($r->trip_type) {
                    \App\Models\TripTicketPrice::TYPE_ONE_WAY => 'ida',
                    \App\Models\TripTicketPrice::TYPE_REGRESO => 'regreso',
                    \App\Models\TripTicketPrice::TYPE_ESPECIAL => 'especial',
                    default => 'redondo',
                };
            };
        @endphp

        @foreach ($seatsByDeck as $deck => $deckSeats)
        @if ($unit->has_upper_deck)
            <p class="deck-title">{{ $deckNames[$deck] ?? $deck }} &nbsp; UNIDAD {{ $unit->name }}</p>
        @endif
        @php
            // Matches the bus's real seat distribution: every 4 consecutive
            // seats form one printed block — the first 2 stack in the left
            // column, the next 2 stack in the right column (1/2 left,
            // 3/4 right; 5/6 left, 7/8 right; ...), with a divider line
            // between blocks. $seats already arrives sorted by label and
            // includes every bookable seat, not just the occupied ones.
            $blocks = $deckSeats->chunk(4);
            // "Especial" seats are sold as a mancuerna (2+ seats, ONE charge) —
            // only they get the ESPECIAL tag, and the payment badge prints once
            // per group (on its first seat) so the pair isn't charged as two.
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

                return ['first' => $isFirst, 'labels' => $labels, 'total' => $group->sum(fn ($x) => (float) $x->unit_price), 'abono' => $group->sum(fn ($x) => (float) $x->abono)];
            };
            $typeClass = fn ($r) => match ($r->trip_type) {
                \App\Models\TripTicketPrice::TYPE_ONE_WAY => 'ida',
                \App\Models\TripTicketPrice::TYPE_REGRESO => 'regreso',
                \App\Models\TripTicketPrice::TYPE_ESPECIAL => 'especial',
                default => 'redondo',
            };
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
                            @if ($line === 0)
                                <td class="map-cell" rowspan="{{ $lineCount }}">
                                    @foreach ($block as $k => $mseat)
                                        <span class="mbox {{ $mapTypeClass($mseat->id) }} {{ $k === 2 ? 'gap' : '' }}" style="height: {{ round($lineCount * $lineH - 4) }}px;">{{ $mseat->label }}</span>
                                    @endforeach
                                </td>
                            @endif
                            <td class="col-left">
                                @if ($left->has($line))
                                    @php $seatReservations = $reservationsBySeat->get($left[$line]->id, collect()); @endphp
                                    <span class="check-col"><span class="box"></span></span>
                                    <span class="seat-badge">{{ $left[$line]->label }}</span>
                                    @foreach ($seatReservations as $r)
                                        @if (! $loop->first)<span class="name-sep">/</span>@endif
                                        <span class="name {{ $typeClass($r) }}">{{ $r->customer_display_name }}</span>
                                        @php $esp = $especialTag($r); @endphp
                                        @if ($esp)
                                            <span class="pay-badge especial">ESPECIAL {{ $esp['labels'] }}</span>
                                        @endif
                                        @if (! $esp || $esp['first'])
                                            <span class="pay-badge {{ $r->isPaymentCompleted() ? 'paid' : 'pending' }}">{{ $r->isPaymentCompleted() ? 'PAGADO' : 'PENDIENTE' }}@if (! $r->isPaymentCompleted()) ${{ number_format(max(0, ($esp ? $esp['total'] - $esp['abono'] : (float) $r->unit_price - (float) $r->abono)), 2) }}@endif</span>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                            <td class="col-right">
                                @if ($right->has($line))
                                    @php $seatReservations = $reservationsBySeat->get($right[$line]->id, collect()); @endphp
                                    <span class="check-col"><span class="box"></span></span>
                                    <span class="seat-badge">{{ $right[$line]->label }}</span>
                                    @foreach ($seatReservations as $r)
                                        @if (! $loop->first)<span class="name-sep">/</span>@endif
                                        <span class="name {{ $typeClass($r) }}">{{ $r->customer_display_name }}</span>
                                        @php $esp = $especialTag($r); @endphp
                                        @if ($esp)
                                            <span class="pay-badge especial">ESPECIAL {{ $esp['labels'] }}</span>
                                        @endif
                                        @if (! $esp || $esp['first'])
                                            <span class="pay-badge {{ $r->isPaymentCompleted() ? 'paid' : 'pending' }}">{{ $r->isPaymentCompleted() ? 'PAGADO' : 'PENDIENTE' }}@if (! $r->isPaymentCompleted()) ${{ number_format(max(0, ($esp ? $esp['total'] - $esp['abono'] : (float) $r->unit_price - (float) $r->abono)), 2) }}@endif</span>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endfor
                @endforeach
            </tbody>
        </table>
        @endforeach
    @endif
</body>
</html>
