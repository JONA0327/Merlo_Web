<x-admin-layout active="precios" title="Precios de boleto">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Precios de boleto</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Define aquí el costo de cada viaje. Los precios se aplican por defecto al viaje y se usan en el selector de asientos del cliente y en los apartados del administrador.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.precios.update') }}">
        @csrf

        <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            @if ($routes->isEmpty())
                <p class="rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                    Aún no hay viajes registrados. Crea uno primero desde <a href="{{ route('admin.viajes') }}" class="font-semibold text-[#8C1D2B] hover:underline">Viajes</a>.
                </p>
            @else
                <div class="overflow-hidden rounded-2xl ring-1 ring-black/5">
                    <table class="min-w-full divide-y divide-black/5 text-sm">
                        <thead class="bg-[#FFFBF6] text-left text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">
                            <tr>
                                <th class="px-4 py-3">Viaje</th>
                                <th class="px-4 py-3">Fecha</th>
                                @foreach ($tripTypes as $type => $label)
                                    <th class="px-4 py-3">
                                        <div>{{ $label }}</div>
                                        <div class="mt-0.5 text-[10px] font-medium normal-case tracking-normal text-[#2B1113]/40">Precio por boleto</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5 bg-white">
                            @foreach ($routes as $route)
                                @php
                                    $oneWay = $route->prices->firstWhere('trip_type', \App\Models\TripTicketPrice::TYPE_ONE_WAY);
                                    $roundTrip = $route->prices->firstWhere('trip_type', \App\Models\TripTicketPrice::TYPE_ROUND_TRIP);
                                @endphp
                                <tr class="hover:bg-[#FFFBF6]/60 transition-colors">
                                    <td class="px-4 py-3">
                                        <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $route->from }} → {{ $route->to }}</p>
                                        <p class="text-xs text-[#2B1113]/60">{{ $route->duration }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-[#2B1113]/80">
                                        {{ $route->day ? $route->day->format('d/m/Y') : '—' }}
                                        <span class="text-xs text-[#2B1113]/50">· {{ $route->departure_time_formatted ?? 'Sin horario' }}</span>
                                    </td>
                                    @foreach ($tripTypes as $type => $label)
                                        @php $existing = $type === \App\Models\TripTicketPrice::TYPE_ONE_WAY ? $oneWay : $roundTrip; @endphp
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[#2B1113]/40">$</span>
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="prices[{{ $route->id }}][{{ $type }}]"
                                                    value="{{ $existing && (float) $existing->price > 0 ? number_format((float) $existing->price, 2, '.', '') : '' }}"
                                                    placeholder="0.00"
                                                    class="w-28 rounded-lg border border-black/10 bg-[#FFFBF6] px-2.5 py-1.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none"
                                                >
                                                <label class="flex cursor-pointer items-center gap-1 text-[11px] font-semibold text-[#2B1113]/60">
                                                    <input
                                                        type="checkbox"
                                                        name="prices[{{ $route->id }}][is_active][{{ $type }}]"
                                                        value="1"
                                                        @checked($existing?->is_active ?? false)
                                                        class="h-3.5 w-3.5 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]"
                                                    >
                                                    Visible
                                                </label>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-between gap-4">
                    <p class="text-xs text-[#2B1113]/50">Si dejas el precio vacío, ese tipo de boleto no se ofrece para el viaje. El checkbox <strong>Visible</strong> lo apaga sin perder el valor.</p>
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                        Guardar precios
                    </button>
                </div>
            @endif
        </div>
    </form>
</x-admin-layout>
