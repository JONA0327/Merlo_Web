<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Lista de asientos</title>
@php
    // Everything must fit on ONE sheet: the more seats there are, the tighter
    // the list rows get (a 64-seat bus is 32 printed lines).
    $printedRows = $seats->groupBy('deck')->sum(fn ($g) => (int) ceil($g->count() / 2));
    $tight = $printedRows > 28;
    $pad = $tight ? 1 : ($printedRows > 20 ? 3 : 6);
    $fs = $tight ? 10 : ($printedRows > 20 ? 11 : 13);
    $bodyPad = $tight ? 14 : 24;
@endphp
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #2B1113; margin: 0; padding: {{ $bodyPad }}px; font-size: 12px; }
    .header h1 { font-size: {{ $tight ? 15 : 18 }}px; margin: 0 0 2px 0; }
    .header p { font-size: 11px; color: #6b5c5e; margin: 0 0 2px 0; }
    .header .meta { font-size: 9px; color: #9a8a8c; margin-top: {{ $tight ? 3 : 8 }}px; }
    table.manifest { width: 100%; border-collapse: collapse; margin-top: 16px; }
    table.manifest td { padding: {{ $pad }}px 8px; vertical-align: middle; width: 50%; }
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
    /* Mapa a la izquierda y lista a la derecha en una tabla (DomPDF no
       acomoda bien una tabla junto a un float: estiraba la primera fila). */
    table.layout { width: 100%; border-collapse: collapse; margin-top: {{ $tight ? 4 : 12 }}px; }
    table.layout td.map-col { width: 250px; padding: 0 16px 0 0; vertical-align: top; }
    table.layout td.list-col { padding: 0; vertical-align: top; }
    table.manifest { margin-top: 0; }
    table.manifest tr { page-break-inside: avoid; }
    .map-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #6b5c5e; margin: 0 0 4px 0; }
    .map-box { position: relative; border: 1px solid #c9bfc1; background: #FFFBF6; margin-bottom: 12px; }
    .map-seat { position: absolute; border: 1px solid #15803D; background: #ffffff; text-align: center; font-size: 7px; font-weight: bold; color: #2B1113; overflow: hidden; }
    .map-seat.ida { background: #bae6fd; border-color: #075985; }
    .map-seat.regreso { background: #fecaca; border-color: #991b1b; }
    .map-seat.especial { background: #a7f3d0; border-color: #065f46; }
    .map-seat.redondo { background: #e5d5d7; border-color: #2B1113; }
    .deck-title { font-size: 12px; font-weight: bold; margin: 6px 0 0 0; color: #8C1D2B; }
    .empty { padding: 18px; text-align: center; color: #9a8a8c; }
    .pay-badge { display: inline-block; border-radius: 10px; padding: {{ $tight ? 0 : 1 }}px 8px; font-size: {{ $tight ? 8 : 9 }}px; font-weight: bold; margin-left: 4px; }
    .pay-badge.paid { background: #d1fae5; color: #065f46; }
    .pay-badge.pending { background: #fef3c7; color: #92400e; }
    .pay-badge.especial { background: #d1fae5; color: #065f46; border: 1px solid #065f46; }
    .legend { margin-top: {{ $tight ? 3 : 10 }}px; }
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
            $unit = $trip->busUnit;
            $deckNames = ['lower' => 'Piso inferior', 'upper' => 'Piso superior'];
            $seatsByDeck = $seats->groupBy('deck')->sortKeys();
            $mapWidth = 250;
            // Cap the map's height too, so a tall canvas never pushes the
            // sheet onto a second page (each deck gets its share of 520px).
            $maxMapHeight = 520 / max(1, $seatsByDeck->count());
            $scale = min($mapWidth / max(1, (float) $unit->canvas_width), $maxMapHeight / max(1, (float) $unit->canvas_height));
            $mapBoxWidth = round($unit->canvas_width * $scale);
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

        <table class="layout"><tr>
        <td class="map-col">
            @foreach ($seatsByDeck as $deck => $deckSeats)
                @if ($unit->has_upper_deck)
                    <p class="map-title">{{ $deckNames[$deck] ?? $deck }}</p>
                @endif
                <div class="map-box" style="width: {{ $mapBoxWidth }}px; height: {{ round($unit->canvas_height * $scale) }}px;">
                    @foreach ($deckSeats as $seat)
                        <div class="map-seat {{ $mapTypeClass($seat->id) }}" style="left: {{ round($seat->pos_x * $scale) }}px; top: {{ round($seat->pos_y * $scale) }}px; width: {{ max(10, round($seat->width * $scale)) }}px; height: {{ max(10, round($seat->height * $scale)) }}px; line-height: {{ max(10, round($seat->height * $scale)) }}px;">{{ $seat->label }}</div>
                    @endforeach
                </div>
            @endforeach
        </td>

        <td class="list-col">
        @foreach ($seatsByDeck as $deck => $deckSeats)
        @if ($unit->has_upper_deck)
            <p class="deck-title">{{ $deckNames[$deck] ?? $deck }}</p>
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
        </td>
        </tr></table>
    @endif
</body>
</html>
