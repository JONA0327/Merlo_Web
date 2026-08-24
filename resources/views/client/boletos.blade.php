<x-client-layout active="boletos" title="Mis boletos">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Mis boletos</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Tus boletos comprados y próximos viajes.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @forelse ($reservations as $landingRouteId => $group)
        @php $trip = $group->first()->landingRoute; @endphp
        <div class="mb-4 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <p class="font-[Poppins] text-xl font-extrabold text-[#2B1113]">{{ $trip->from }}</p>
                        <svg class="h-4 w-4 text-[#8C1D2B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 10a.75.75 0 01.75-.75h11.19l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.25A.75.75 0 012.5 10z" clip-rule="evenodd"/></svg>
                        <p class="font-[Poppins] text-xl font-extrabold text-[#2B1113]">{{ $trip->to }}</p>
                    </div>
                    <p class="mt-2 text-sm text-[#2B1113]/60">
                        {{ $trip->day ? $trip->day->format('d/m/Y') : 'Sin fecha' }} · {{ $trip->departure_time_formatted ?? 'Sin horario' }} · {{ $trip->duration }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($group as $reservation)
                        <span class="rounded-full bg-[#8C1D2B]/10 px-3 py-1.5 text-xs font-bold text-[#8C1D2B]">
                            Asiento {{ $reservation->seat->label }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <x-empty-state
            title="Aún no has reservado boletos"
            description="Cuando reserves un boleto, aparecerá aquí con los detalles de tu viaje."
        >
            <x-slot name="icon">
                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 3.75a3 3 0 00-3 3v.75a.75.75 0 00.75.75 1.5 1.5 0 010 3 .75.75 0 00-.75.75v.75a3 3 0 003 3h11a3 3 0 003-3v-.75a.75.75 0 00-.75-.75 1.5 1.5 0 010-3 .75.75 0 00.75-.75v-.75a3 3 0 00-3-3h-11zM8 6a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 018 6zm.75 3.25a.75.75 0 00-1.5 0v.5a.75.75 0 001.5 0v-.5zM8 12a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 018 12z" clip-rule="evenodd"/></svg>
            </x-slot>
        </x-empty-state>

        <div class="mt-4 text-center">
            <a href="{{ url('/#buscar') }}" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                Comprar boleto
            </a>
        </div>
    @endforelse
</x-client-layout>
