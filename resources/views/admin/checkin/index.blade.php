<x-admin-layout active="checkin" title="Check-in de viaje">
    <div class="mb-8">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Check-in de viaje</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Escanea el código QR del cliente o dicta su ticket code para registrar la subida. Para boletos redondos, escanea otra vez al regreso.</p>
    </div>

    <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.checkin.lookup') }}" class="space-y-3">
            @csrf
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">Ticket code</span>
                <input type="text" name="code" autofocus required placeholder="XXXXXX-XXXXXX-XXXXXX-..." class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 font-mono text-sm tracking-wider text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none uppercase">
            </label>
            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm6-9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1V4zm0 9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3zm6-9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1V4zm0 9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3zM2 14a1 1 0 011-1h16a1 1 0 011 1v3a1 1 0 01-1 1H3a1 1 0 01-1-1v-3z" clip-rule="evenodd"/></svg>
                Buscar boleto
            </button>
        </form>
    </div>

    <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Pendientes de check-in</h3>
        <p class="mt-1 text-xs text-[#2B1113]/60">Reservaciones que aún no han registrado su subida. Las redondas aparecen dos veces (una por pierna).</p>

        @php
            $pending = \App\Models\SeatReservation::query()
                ->with(['landingRoute', 'seat'])
                ->pendingCheckIn()
                ->orderBy('created_at')
                ->limit(50)
                ->get();
        @endphp

        @if ($pending->isEmpty())
            <p class="mt-4 text-sm text-[#2B1113]/50">No hay boletos pendientes de check-in.</p>
        @else
            <ul class="mt-4 divide-y divide-black/5">
                @foreach ($pending as $r)
                    <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-[#2B1113]">{{ $r->customer_display_name }} <span class="ml-1 rounded bg-[#FFFBF6] px-1.5 py-0.5 text-[10px] font-mono text-[#2B1113]/70 ring-1 ring-black/10">{{ $r->seat?->label ?? '—' }}</span></p>
                            <p class="text-xs text-[#2B1113]/60">{{ $r->landingRoute?->from }} → {{ $r->landingRoute?->to }} &middot; {{ $r->trip_type_label }}</p>
                        </div>
                        <a href="{{ route('admin.checkin.scan', $r->ticket_code) }}" class="rounded-lg bg-[#FFFBF6] px-2.5 py-1 text-[11px] font-bold text-[#8C1D2B] ring-1 ring-black/10 hover:bg-[#8C1D2B]/5">Abrir</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-admin-layout>
