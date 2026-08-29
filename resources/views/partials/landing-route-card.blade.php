{{--
    Shared card body for a featured landing route — extracted so the welcome
    page (where the whole card is the link) and any future "list of routes"
    surface can render the same trip summary without duplicating markup.

    Expects: $route — a \App\Models\LandingRoute instance.
--}}
<div class="flex items-center justify-between">
    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8C1D2B]/10 text-[#8C1D2B]">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v6a2 2 0 002 2h1.05a2.5 2.5 0 014.9 0H12a1 1 0 001-1v-2h2.05a1 1 0 00.923-.617l1.027-2.47A1 1 0 0016.028 6H14V5a1 1 0 00-1-1H3z"/><path d="M6 15.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
    </span>
    <span class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Directo</span>
</div>
<p class="mt-5 font-[Poppins] text-base font-bold text-[#2B1113]">{{ $route->from }}</p>
<div class="my-2 flex items-center gap-2 text-[#2B1113]/30">
    <span class="h-1.5 w-1.5 rounded-full bg-[#F5B301]"></span>
    <span class="h-px flex-1 border-t border-dashed border-current"></span>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 10a.75.75 0 01.75-.75h11.19l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.25A.75.75 0 012.5 10z" clip-rule="evenodd"/></svg>
    <span class="h-1.5 w-1.5 rounded-full bg-[#8C1D2B]"></span>
</div>
<p class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $route->to }}</p>

<div class="mt-5 flex items-center justify-between border-t border-black/5 pt-4">
    <div>
        <p class="text-xs text-[#2B1113]/50">Duración</p>
        <p class="text-sm font-semibold">{{ $route->duration }}</p>
    </div>
    <div class="text-right space-y-0.5">
        <p class="text-xs text-[#2B1113]/50">Desde</p>
        <p class="font-[Poppins] text-base font-extrabold text-[#8C1D2B]">{{ $route->formattedPriceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY) ?? '—' }} <span class="text-[10px] font-semibold text-[#2B1113]/40">ida</span></p>
        @if ($route->formattedPriceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP))
            <p class="font-[Poppins] text-sm font-bold text-[#8C1D2B]/80">{{ $route->formattedPriceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP) }} <span class="text-[10px] font-semibold text-[#2B1113]/40">redondo</span></p>
        @endif
    </div>
</div>

<div class="mt-3 space-y-0.5">
    @if ($route->day)
        <p class="text-xs font-semibold text-[#2B1113]/50">Salida: {{ $route->day->format('d/m/Y') }}@if ($route->departure_time_formatted) &middot; {{ $route->departure_time_formatted }} @endif</p>
    @endif
    @if ($route->return_date)
        <p class="text-xs font-semibold text-[#2B1113]/50">Regreso: {{ $route->return_date->format('d/m/Y') }}</p>
    @endif
</div>
