@php
    $trip = $reservation->landingRoute;
    $isOxxo = $reservation->payment_method === \App\Models\SeatReservation::PAYMENT_METHOD_OXXO;
    $isSpei = $reservation->payment_method === \App\Models\SeatReservation::PAYMENT_METHOD_SPEI;
    $isCash = $isOxxo || $isSpei;

    $expiresAt = $reservation->openpay_expires_at;
    $expiresIn = $expiresAt ? max(0, now()->diffInHours($expiresAt, false)) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago pendiente · Merlo Transportes</title>
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
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#F5B301]/25 ring-4 ring-[#F5B301]/40">
                <svg class="h-8 w-8 text-[#A16207]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
            </div>
            <p class="mt-5 text-xs font-bold uppercase tracking-[0.22em] text-[#A16207]">Pago pendiente</p>
            <h1 class="mt-2 font-[Poppins] text-3xl font-extrabold text-[#2B1113]">
                @if ($isOxxo)
                    Paga en OXXO para confirmar tu boleto
                @elseif ($isSpei)
                    Realiza una transferencia SPEI para confirmar tu boleto
                @else
                    Tu pago está siendo procesado
                @endif
            </h1>
            <p class="mx-auto mt-3 max-w-md text-sm text-[#2B1113]/60">
                @if ($isOxxo)
                    Lleva el código de barras a cualquier tienda OXXO. El cargo se reflejará en cuanto se acredite el pago.
                @elseif ($isSpei)
                    Haz una transferencia por el monto exacto a la CLABE indicada. Te confirmaremos en cuanto se reciba.
                @else
                    Te avisaremos por correo cuando se confirme.
                @endif
            </p>
        </div>

        @if ($isOxxo && $reservation->openpay_barcode_url)
            <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">Código de barras para OXXO</h2>
                <div class="mt-4 flex flex-col items-center gap-3">
                    <img src="{{ $reservation->openpay_barcode_url }}" alt="Código de barras OXXO" class="h-32 w-full max-w-md rounded-xl bg-white p-3 ring-1 ring-black/10">
                    <p class="text-center text-xs text-[#2B1113]/60">Muestra este código de barras en la caja. Vence en {{ $expiresAt?->format('d/m/Y') ?? '2 días' }}.</p>
                </div>
            </div>
        @endif

        @if ($isSpei && $reservation->openpay_payment_url)
            @php
                // OpenPay's bank_account charges return a payment_method.url that
                // contains the CLABE plus bank instructions. We display it as
                // a CLABE block for copy/paste, plus a "ver instrucciones" link.
                $speiUrl = $reservation->openpay_payment_url;
                $speiClabe = null;
                if (preg_match('/(\d{18})/', $speiUrl, $m)) {
                    $speiClabe = $m[1];
                }
            @endphp
            <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">Datos para transferencia SPEI</h2>
                @if ($speiClabe)
                    <div class="mt-4 rounded-2xl bg-[#FFFBF6] p-4 ring-1 ring-black/10">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">CLABE interbancaria</p>
                        <p class="mt-1 select-all break-all font-mono text-base font-bold text-[#2B1113]">{{ $speiClabe }}</p>
                    </div>
                @endif
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Monto exacto</dt><dd class="font-bold text-[#2B1113]">${{ number_format($reservation->total, 2) }} MXN</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Referencia</dt><dd class="font-mono text-xs">{{ $reservation->ticket_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Vence</dt><dd>{{ $expiresAt?->format('d/m/Y H:i') ?? 'Mañana' }}</dd></div>
                </dl>
                <a href="{{ $speiUrl }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6F1622]">
                    Ver instrucciones completas
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                </a>
            </div>
        @endif

        {{-- Reservation summary --}}
        <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <div class="bg-[#2B1113] px-6 py-4 text-white">
                <div class="flex items-center justify-between">
                    <p class="font-[Poppins] text-lg font-bold">Tu reservación</p>
                    <p class="text-xs font-semibold opacity-80">Ticket #{{ $reservation->id }}</p>
                </div>
            </div>
            <div class="bg-[#F5B301] px-6 py-3 text-[#2B1113]">
                <p class="font-[Poppins] text-lg font-extrabold">{{ $trip->from ?? '' }} → {{ $trip->to ?? '' }}</p>
                <p class="text-xs font-semibold opacity-80">{{ $trip->day?->format('d/m/Y') ?? '—' }} · {{ $trip->departure_time_formatted ?? '—' }} · {{ $reservation->trip_type_label }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 p-6 text-sm sm:grid-cols-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Pasajero</p>
                    <p class="mt-1 font-bold text-[#2B1113]">{{ $reservation->customer_display_name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Asiento</p>
                    <p class="mt-1 font-bold text-[#2B1113]">{{ $reservation->seat?->label ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Método</p>
                    <p class="mt-1 font-bold text-[#2B1113]">{{ $reservation->payment_method_label }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Total</p>
                    <p class="mt-1 font-[Poppins] font-extrabold text-[#8C1D2B]">${{ number_format($reservation->total, 2) }}</p>
                </div>
            </div>
        </div>

        {{-- What happens next --}}
        <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">¿Qué sigue?</h2>
            <ol class="mt-3 space-y-2 text-sm text-[#2B1113]/70">
                @if ($isOxxo)
                    <li>1. Lleva el código de barras a cualquier tienda OXXO antes del <strong>{{ $expiresAt?->format('d/m/Y') ?? 'plazo' }}</strong>.</li>
                    <li>2. Paga <strong>${{ number_format($reservation->total, 2) }} MXN</strong> en efectivo.</li>
                    <li>3. Te enviaremos un correo con tu boleto y QR en cuanto OXXO confirme el pago.</li>
                @elseif ($isSpei)
                    <li>1. Desde tu banca en línea, transfiere <strong>${{ number_format($reservation->total, 2) }} MXN</strong> a la CLABE.</li>
                    <li>2. Usa la referencia <strong>{{ $reservation->ticket_code }}</strong> para identificar el pago.</li>
                    <li>3. Te avisaremos por correo en cuanto se reciba la transferencia.</li>
                @else
                    <li>Te avisaremos por correo cuando el pago se confirme.</li>
                @endif
            </ol>
        </div>

        <p class="mt-6 text-center text-xs text-[#2B1113]/40">
            ¿Dudas? Escríbenos a <a href="mailto:soporte@merlo.com.mx" class="text-[#8C1D2B] hover:underline">soporte@merlo.com.mx</a>
        </p>
    </main>
</body>
</html>
