<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago confirmado · Merlo Transportes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FFFBF6] text-[#2B1113] antialiased">

    {{-- Header --}}
    <header class="border-b border-black/5 bg-white">
        <div class="mx-auto flex h-20 max-w-3xl items-center justify-between px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-10 w-auto">
            </a>
            <a href="{{ route('cliente.boletos') }}" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                Mis boletos
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-12">

        {{-- Hero --}}
        <div class="rounded-3xl bg-white p-8 text-center ring-1 ring-black/5 shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#22C55E]/15 ring-4 ring-[#22C55E]/25">
                <svg class="h-8 w-8 text-[#15803D]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.997 7.997a1 1 0 01-1.414 0L3.296 10.71a1 1 0 011.414-1.42l3.297 3.296 7.29-7.296a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </div>
            <p class="mt-5 text-xs font-bold uppercase tracking-[0.22em] text-[#15803D]">Pago confirmado</p>
            <h1 class="mt-2 font-[Poppins] text-3xl font-extrabold text-[#2B1113]">¡Tu boleto está listo!</h1>
            <p class="mx-auto mt-3 max-w-md text-sm text-[#2B1113]/60">
                Te enviamos un correo con el boleto y el código QR para el check-in.
                También puedes consultarlo cuando quieras desde "Mis boletos".
            </p>
        </div>

        {{-- Ticket card --}}
        @php
            $trip = $reservation->landingRoute;
            $seats = $reservation->user_id
                ? $reservation->user->seatReservations()
                    ->where('landing_route_id', $trip->id)
                    ->where('openpay_charge_id', $reservation->openpay_charge_id)
                    ->with('seat')
                    ->get()
                : collect([$reservation->load('seat')]);
        @endphp

        <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <div class="bg-[#8C1D2B] px-6 py-4 text-white">
                <div class="flex items-center justify-between">
                    <p class="font-[Poppins] text-lg font-bold">Merlo Transportes</p>
                    <p class="text-xs font-semibold opacity-80">Ticket #{{ $reservation->id }}</p>
                </div>
                <p class="mt-1 text-xs opacity-80">Ticket code: <span class="font-mono">{{ $reservation->ticket_code }}</span></p>
            </div>

            <div class="bg-[#F5B301] px-6 py-3 text-[#2B1113]">
                <p class="font-[Poppins] text-xl font-extrabold">
                    {{ $trip->from ?? '' }} → {{ $trip->to ?? '' }}
                </p>
                <p class="text-xs font-semibold opacity-80">
                    {{ $trip->day?->format('d/m/Y') ?? 'Sin fecha' }}
                    · {{ $trip->departure_time_formatted ?? 'Sin horario' }}
                    · {{ $reservation->trip_type_label }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Pasajero</p>
                    <p class="mt-1 text-sm font-bold text-[#2B1113]">{{ $reservation->customer_display_name }}</p>
                    <p class="text-xs text-[#2B1113]/60">{{ $reservation->customer_display_email }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Asientos</p>
                    <p class="mt-1 text-sm font-bold text-[#2B1113]">
                        @foreach ($seats as $s)
                            <span class="mr-1 inline-block rounded bg-[#FFFBF6] px-2 py-0.5 ring-1 ring-black/10">{{ $s->seat?->label ?? '—' }}</span>
                        @endforeach
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Pagado</p>
                    <p class="mt-1 text-sm font-bold text-[#2B1113]">{{ $reservation->payment_method_label }} @if($reservation->payment_method_detail)<span class="text-[#2B1113]/60">· {{ $reservation->payment_method_detail }}</span>@endif</p>
                    <p class="text-xs text-[#2B1113]/60">${{ number_format($reservation->total, 2) }} MXN</p>
                </div>
            </div>

            @php
                $checkinUrl = route('admin.checkin.scan', $reservation->ticket_code);
            @endphp
            <div class="border-t border-black/5 bg-[#FFFBF6] p-6 text-center">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($checkinUrl) }}" alt="QR del boleto" class="mx-auto h-44 w-44 rounded-xl bg-white p-2 ring-1 ring-black/10">
                <p class="mt-3 text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Muestra este QR al abordar</p>
            </div>
        </div>

        {{-- Receipt breakdown --}}
        <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">Detalle del pago</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-[#2B1113]/60">Subtotal</dt><dd class="font-semibold">${{ number_format($reservation->subtotal ?? 0, 2) }} MXN</dd></div>
                <div class="flex justify-between"><dt class="text-[#2B1113]/60">IVA (16%)</dt><dd class="font-semibold">${{ number_format($reservation->tax ?? 0, 2) }} MXN</dd></div>
                <div class="flex justify-between border-t border-black/5 pt-2 text-base"><dt class="font-bold">Total</dt><dd class="font-[Poppins] font-extrabold text-[#8C1D2B]">${{ number_format($reservation->total ?? 0, 2) }} MXN</dd></div>
                @if ($reservation->openpay_authorization)
                    <div class="flex justify-between pt-3 text-xs text-[#2B1113]/50"><dt>Autorización</dt><dd class="font-mono">{{ $reservation->openpay_authorization }}</dd></div>
                @endif
                @if ($reservation->openpay_charge_id)
                    <div class="flex justify-between text-xs text-[#2B1113]/50"><dt>ID de cargo OpenPay</dt><dd class="font-mono">{{ $reservation->openpay_charge_id }}</dd></div>
                @endif
                <div class="flex justify-between text-xs text-[#2B1113]/50"><dt>Pagado el</dt><dd>{{ $reservation->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>
        </div>

        <p class="mt-6 text-center text-xs text-[#2B1113]/40">
            ¿Necesitas ayuda? Escríbenos a <a href="mailto:soporte@merlo.com.mx" class="text-[#8C1D2B] hover:underline">soporte@merlo.com.mx</a>
        </p>
    </main>
</body>
</html>
