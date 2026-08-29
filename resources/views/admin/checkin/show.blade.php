<?php /** @var ?\App\Models\SeatReservation $reservation */ ?>
<x-admin-layout active="checkin" title="Check-in — {{ $reservation?->customer_display_name ?? 'Sin resultados' }}">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.checkin.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#8C1D2B] hover:text-[#6F1622]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17.5 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h11.19A.75.75 0 0117.5 10z" clip-rule="evenodd"/></svg>
                Volver a la búsqueda
            </a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">
                @if ($reservation) Check-in &middot; {{ $reservation->customer_display_name }} @else Código no encontrado @endif
            </h2>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @if (! $reservation)
        <div class="rounded-3xl bg-white p-8 text-center ring-1 ring-black/5 shadow-sm">
            <svg class="mx-auto h-12 w-12 text-red-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <p class="mt-3 text-sm font-semibold text-[#2B1113]">No se encontró ningún boleto con el código</p>
            <p class="mt-1 font-mono text-xs text-[#2B1113]/60 break-all">{{ $code }}</p>
            <a href="{{ route('admin.checkin.index') }}" class="mt-4 inline-flex rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6F1622]">Intentar de nuevo</a>
        </div>
    @else
        @php
            $trip = $reservation->landingRoute;
            $seat = $reservation->seat;
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data='.urlencode(route('admin.checkin.scan', $reservation->ticket_code, true));
        @endphp

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
            <div class="lg:col-span-7 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $reservation->isRoundTrip() ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">{{ $reservation->trip_type_label }}</span>
                    <span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $reservation->isSent() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $reservation->status }}</span>
                </div>

                <h3 class="mt-3 font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $trip->from }} <span class="text-[#2B1113]/40">→</span> {{ $trip->to }}</h3>
                <p class="text-sm text-[#2B1113]/60">{{ $trip->day?->format('d/m/Y') ?? '—' }} &middot; {{ $trip->departure_time_formatted ?? '—' }} &middot; {{ $trip->duration }}</p>
                @if ($reservation->isRoundTrip() && $trip->return_date)
                    <p class="text-sm text-[#2B1113]/60">Regreso: {{ $trip->return_date->format('d/m/Y') }}</p>
                @endif

                <dl class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div class="rounded-xl bg-[#FFFBF6] p-3">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Pasajero</dt>
                        <dd class="mt-1 text-sm font-bold text-[#2B1113]">{{ $reservation->customer_display_name }}</dd>
                        <dd class="text-[11px] text-[#2B1113]/60 break-all">{{ $reservation->customer_display_email ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-[#FFFBF6] p-3">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Asiento</dt>
                        <dd class="mt-1 text-sm font-bold text-[#2B1113]">{{ $seat?->label ?? '—' }}</dd>
                        <dd class="text-[11px] text-[#2B1113]/60">Precio: ${{ number_format((float) ($reservation->unit_price ?? 0), 2) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-black/5 pt-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">Verificación</h4>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @php
                            $outboundDone = $reservation->isOutboundVerified();
                            $returnVisible = $reservation->isRoundTrip();
                            $returnDone = $reservation->isReturnVerified();
                        @endphp
                        <div class="rounded-xl border-2 {{ $outboundDone ? 'border-emerald-300 bg-emerald-50' : 'border-dashed border-amber-300 bg-amber-50' }} p-4">
                            <div class="flex items-center gap-2">
                                @if ($outboundDone)
                                    <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-8 8a1 1 0 01-1.42 0l-4-4a1 1 0 011.42-1.42L8 12.585l7.296-7.295a1 1 0 011.408 0z" clip-rule="evenodd"/></svg>
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">Salida registrada</span>
                                @else
                                    <svg class="h-5 w-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                                    <span class="text-xs font-bold uppercase tracking-wider text-amber-800">Pendiente</span>
                                @endif
                            </div>
                            @if ($outboundDone)
                                <p class="mt-2 text-[11px] text-emerald-700">{{ $reservation->outbound_verified_at->format('d/m/Y H:i') }} hrs</p>
                                <p class="text-[10px] text-emerald-700/80">por {{ $reservation->outboundVerifiedBy?->name ?? 'operador' }}</p>
                            @endif
                            @if (! $outboundDone)
                                <form method="POST" action="{{ route('admin.checkin.outbound', $reservation) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#F5B301] px-3 py-2 text-xs font-bold text-[#2B1113] shadow-sm hover:bg-[#E0A400] transition-colors">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-8 8a1 1 0 01-1.42 0l-4-4a1 1 0 011.42-1.42L8 12.585l7.296-7.295a1 1 0 011.408 0z" clip-rule="evenodd"/></svg>
                                        Registrar salida
                                    </button>
                                </form>
                            @endif
                        </div>

                        @if ($returnVisible)
                            <div class="rounded-xl border-2 {{ $returnDone ? 'border-emerald-300 bg-emerald-50' : 'border-dashed border-amber-300 bg-amber-50' }} p-4">
                                <div class="flex items-center gap-2">
                                    @if ($returnDone)
                                        <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-8 8a1 1 0 01-1.42 0l-4-4a1 1 0 011.42-1.42L8 12.585l7.296-7.295a1 1 0 011.408 0z" clip-rule="evenodd"/></svg>
                                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">Regreso registrado</span>
                                    @else
                                        <svg class="h-5 w-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                                        <span class="text-xs font-bold uppercase tracking-wider text-amber-800">Pendiente</span>
                                    @endif
                                </div>
                                @if ($returnDone)
                                    <p class="mt-2 text-[11px] text-emerald-700">{{ $reservation->return_verified_at->format('d/m/Y H:i') }} hrs</p>
                                    <p class="text-[10px] text-emerald-700/80">por {{ $reservation->returnVerifiedBy?->name ?? 'operador' }}</p>
                                @else
                                    <form method="POST" action="{{ route('admin.checkin.return', $reservation) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#F5B301] px-3 py-2 text-xs font-bold text-[#2B1113] shadow-sm hover:bg-[#E0A400] transition-colors disabled:opacity-40" {{ $outboundDone ? '' : 'disabled' }}>
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-8 8a1 1 0 01-1.42 0l-4-4a1 1 0 011.42-1.42L8 12.585l7.296-7.295a1 1 0 011.408 0z" clip-rule="evenodd"/></svg>
                                            Registrar regreso
                                        </button>
                                    </form>
                                    @if (! $outboundDone)
                                        <p class="mt-1 text-[10px] text-amber-700">Registra primero la salida</p>
                                    @endif
                                @endif
                            </div>
                        @else
                            <div class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Sin regreso</p>
                                <p class="mt-1 text-[11px] text-slate-500">Este boleto es solo de ida.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5 space-y-4">
                <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Código del boleto</h3>
                    <div class="mt-3 flex justify-center">
                        <img src="{{ $qrUrl }}" alt="QR del boleto" class="h-44 w-44 rounded-lg border border-black/5">
                    </div>
                    <p class="mt-3 break-all rounded-lg bg-[#FFFBF6] p-3 text-center font-mono text-xs font-bold tracking-wider text-[#2B1113] ring-1 ring-black/10">
                        {{ $reservation->ticket_code }}
                    </p>
                </div>
                @if ($reservation->notes)
                    <div class="rounded-3xl bg-[#F5B301]/10 p-4 ring-1 ring-[#F5B301]/30">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-[#2B1113]/70">Notas del apartado</h3>
                        <p class="mt-2 text-sm text-[#2B1113]">{{ $reservation->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-admin-layout>
