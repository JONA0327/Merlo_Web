<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago no procesado · Merlo Transportes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FFFBF6] text-[#2B1113] antialiased">

    {{-- Header --}}
    <header class="border-b border-black/5 bg-white">
        <div class="mx-auto flex h-20 max-w-3xl items-center justify-between px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-10 w-auto">
            </a>
            @auth
                <a href="{{ route('cliente.boletos') }}" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                    Mis boletos
                </a>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-12">

        {{-- Hero --}}
        <div class="rounded-3xl bg-white p-8 text-center ring-1 ring-black/5 shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#EF4444]/15 ring-4 ring-[#EF4444]/25">
                <svg class="h-8 w-8 text-[#991B1B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
            </div>
            <p class="mt-5 text-xs font-bold uppercase tracking-[0.22em] text-[#991B1B]">No se pudo procesar el pago</p>
            <h1 class="mt-2 font-[Poppins] text-3xl font-extrabold text-[#2B1113]">Intenta de nuevo</h1>
            <p class="mx-auto mt-3 max-w-md text-sm text-[#2B1113]/60">
                {{ session('error') ?: 'Tu banco rechazó la operación o hubo un problema con el cargo. Los asientos se liberaron.' }}
            </p>
        </div>

        {{-- Reservation detail --}}
        @if ($reservation->id)
            <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">Detalle del intento</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Folio</dt><dd class="font-mono">#{{ $reservation->id }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Viaje</dt><dd class="font-bold">{{ $reservation->landingRoute?->from }} → {{ $reservation->landingRoute?->to }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Fecha</dt><dd>{{ $reservation->landingRoute?->day?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[2B1113]/60">Método</dt><dd class="font-bold">{{ $reservation->payment_method_label }}</dd></div>
                    @if ($reservation->openpay_charge_id)
                        <div class="flex justify-between"><dt class="text-[#2B1113]/60">ID OpenPay</dt><dd class="font-mono text-xs">{{ $reservation->openpay_charge_id }}</dd></div>
                    @endif
                </dl>
            </div>
        @endif

        {{-- Why it failed + what to do --}}
        <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">¿Por qué pudo haber fallado?</h2>
            <ul class="mt-3 space-y-2 text-sm text-[#2B1113]/70">
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#8C1D2B]"></span>
                    <span>Los datos de la tarjeta no coinciden o están vencidos.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#8C1D2B]"></span>
                    <span>Tu banco detectó actividad inusual y bloqueó el cargo.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#8C1D2B]"></span>
                    <span>Fondos insuficientes o límite diario alcanzado.</span>
                </li>
            </ul>
        </div>

        {{-- CTAs --}}
        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <a href="{{ route('travel.seats', ['landingRoute' => $reservation->landing_route_id, 'type' => $reservation->trip_type]) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors sm:w-auto">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 1.414L7.414 9H17a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                Elegir asientos de nuevo
            </a>
            <a href="mailto:soporte@merlo.com.mx" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[#8C1D2B] bg-white px-6 py-3 text-sm font-bold text-[#8C1D2B] hover:bg-[#8C1D2B]/5 transition-colors sm:w-auto">
                Contactar a soporte
            </a>
        </div>

        <p class="mt-8 text-center text-xs text-[#2B1113]/40">
            Si el cargo aparece en tu estado de cuenta, escríbenos a <a href="mailto:soporte@merlo.com.mx" class="text-[#8C1D2B] hover:underline">soporte@merlo.com.mx</a> con el ID de la transacción.
        </p>
    </main>
</body>
</html>
