{{--
    Filtros de la lista de apartados (Apartar asientos y Guías): búsqueda
    por nombre/teléfono, pagado sí/no, categoría y método de pago. Es un
    GET a la misma página — $keep son los query params que la página ya
    usa (p. ej. la fecha de la guía) y deben sobrevivir al filtrar.
--}}
@php
    $filters = $filters ?? [];
    $keep = $keep ?? [];
    $hasFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
    $selectClass = 'w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-xs font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none';
@endphp
<form method="GET" action="{{ url()->current() }}" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
    @foreach ($keep as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por nombre o teléfono…"
           class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-xs text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">

    <select name="paid" class="{{ $selectClass }}" onchange="this.form.submit()">
        <option value="">Pagado: todos</option>
        <option value="yes" @selected(($filters['paid'] ?? '') === 'yes')>Pagados</option>
        <option value="no" @selected(($filters['paid'] ?? '') === 'no')>No pagados</option>
    </select>

    <select name="trip_type" class="{{ $selectClass }}" onchange="this.form.submit()">
        <option value="">Categoría: todas</option>
        @foreach (\App\Models\TripTicketPrice::tripTypes() as $type => $label)
            <option value="{{ $type }}" @selected(($filters['trip_type'] ?? '') === $type)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="payment_method" class="{{ $selectClass }}" onchange="this.form.submit()">
        <option value="">Método: todos</option>
        @foreach (\App\Models\SeatReservation::adminPaymentMethods() as $method => $label)
            <option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ $label }}</option>
        @endforeach
    </select>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6E1622] transition-colors">Buscar</button>
        @if ($hasFilters)
            <a href="{{ url()->current().(collect($keep)->filter(fn ($v) => filled($v))->isNotEmpty() ? '?'.http_build_query(collect($keep)->filter(fn ($v) => filled($v))->all()) : '') }}"
               class="text-xs font-semibold text-[#2B1113]/60 hover:text-[#8C1D2B]">Limpiar</a>
        @endif
    </div>
</form>
