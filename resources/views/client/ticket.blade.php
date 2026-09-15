@php
    $trip = $reservation->landingRoute;
    $seat = $reservation->seat;
    $checkinUrl = \Illuminate\Support\Facades\URL::route('admin.checkin.scan', ['code' => $reservation->ticket_code], true);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=10&data='.urlencode($checkinUrl);

    $returnDate = $trip->return_date?->format('d/m/Y') ?? '—';
    $departure = $trip->departure_time_formatted ?? '—';
    $tripDate = $reservation->isReturnLeg() ? $returnDate : ($trip->day?->format('d/m/Y') ?? '—');
    $isRescheduledReturn = $reservation->isOneWay() && ! $reservation->isReturnLeg() && $reservation->source_reservation_id !== null;
    $tripType = match (true) {
        $reservation->isReturnLeg() => 'Solo regreso',
        $isRescheduledReturn => 'Regreso reprogramado',
        $reservation->isRoundTrip() => 'Viaje redondo',
        default => 'Solo ida',
    };
    $tripTypeBadge = $reservation->isRoundTrip() ? '#F5B301' : '#8C1D2B';
    $legLabel = $reservation->isReturnLeg() || $isRescheduledReturn ? 'regreso' : ($reservation->isRoundTrip() ? 'salida y tu regreso' : 'subida al autobús');
    $seatPrice = (float) $reservation->unit_price / $seatCount;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boleto {{ $seat?->label }} · Merlo Transportes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .ticket-card { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-[#FFFBF6] text-[#2B1113] antialiased">

    <header class="no-print border-b border-black/5 bg-white">
        <div class="mx-auto flex h-20 max-w-2xl items-center justify-between px-6">
            <a href="{{ route('cliente.boletos') }}" class="flex items-center gap-2 text-sm font-bold text-[#8C1D2B] hover:underline">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd"/></svg>
                Mis boletos
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.097 1.126.153A2.212 2.212 0 0118 8.653v4.097A2.25 2.25 0 0115.75 15h-.241l.305 3.05A1 1 0 0114.821 19H5.18a1 1 0 01-.994-1.05L4.491 15H4.25A2.25 2.25 0 012 12.75V8.653c0-1.082.775-2.006 1.874-2.198.374-.056.75-.107 1.126-.153V2.75zm8.5 3.397a41.531 41.531 0 00-7 0V2.75a.25.25 0 01.25-.25h6.5a.25.25 0 01.25.25v3.397zM6.006 15l-.286 2.856a.25.25 0 00.249.279h7.062a.25.25 0 00.249-.28L13.994 15H6.006z" clip-rule="evenodd"/></svg>
                Imprimir
            </button>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-6 py-10 print:py-0">
        @if (session('success'))
            <div class="no-print mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="no-print mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
        @endif
        <div class="ticket-card overflow-hidden rounded-3xl bg-white shadow-xl shadow-black/10 ring-1 ring-black/5">
            {{-- Header --}}
            <div class="flex items-center justify-between px-7 py-5" style="background:#8C1D2B;">
                <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-9 w-auto brightness-0 invert">
                <span class="rounded-full px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wider text-[#2B1113]" style="background:{{ $tripTypeBadge }};">{{ $tripType }}</span>
            </div>

            {{-- Route strip --}}
            <div class="px-7 py-4" style="background:#F5B301;">
                <p class="font-[Poppins] text-2xl font-extrabold tracking-tight text-[#2B1113]">{{ $trip->from }} <span class="opacity-60 font-semibold">→</span> {{ $trip->to }}</p>
                <p class="mt-0.5 text-xs font-semibold text-[#2B1113]/85">{{ $trip->duration }}</p>
            </div>

            {{-- Trip details --}}
            <div class="px-7 pt-6 pb-2">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="font-semibold text-[#8C1D2B]/70">Pasajero</dt>
                        <dd class="font-semibold text-right">{{ $reservation->customer_display_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-semibold text-[#8C1D2B]/70">Salida</dt>
                        <dd class="font-semibold text-right">{{ $tripDate }} &middot; {{ $departure }}</dd>
                    </div>
                    @if ($reservation->isRoundTrip())
                        <div class="flex justify-between">
                            <dt class="font-semibold text-[#8C1D2B]/70">Regreso</dt>
                            <dd class="font-semibold text-right">{{ $returnDate }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="font-semibold text-[#8C1D2B]/70">Precio</dt>
                        <dd class="font-extrabold text-right text-base text-[#8C1D2B]">${{ number_format($seatPrice, 2) }}</dd>
                    </div>
                    @if ($seatCount > 1)
                        <div class="flex justify-between">
                            <dt class="font-semibold text-[#8C1D2B]/70">Compra</dt>
                            <dd class="text-right text-xs text-[#2B1113]/50">{{ $seatCount }} asientos · ${{ number_format((float) $reservation->unit_price, 2) }} total</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Seat --}}
            <div class="px-7 pt-3 pb-2">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-[#8C1D2B]">Asiento</p>
                <span class="inline-block rounded-lg border border-[#8C1D2B] bg-white px-3 py-1 font-bold text-[#8C1D2B]">{{ $seat?->label ?? '—' }}</span>
            </div>

            @if ($reservation->isRoundTrip() && ! $reservation->isReturnVerified())
                {{-- Cambio de fecha de regreso --}}
                <div class="no-print mx-7 mt-3 mb-1 rounded-2xl border border-[#8C1D2B]/15 bg-[#FFFBF6] p-5">
                    @if ($reservation->hasChangedReturn())
                        <p class="text-sm font-semibold text-[#2B1113]">Ya elegiste una nueva fecha de regreso.</p>
                        <a href="{{ route('cliente.boletos.ver', $reservation->return_changed_to_reservation_id) }}" class="mt-2 inline-block text-sm font-bold text-[#8C1D2B] hover:underline">Ver tu nuevo boleto de regreso &rarr;</a>
                    @elseif ($reservation->isReturnVoided())
                        <p class="text-sm font-semibold text-[#8C1D2B]">Tu regreso quedó anulado porque no elegiste una nueva fecha dentro del plazo.</p>
                    @elseif ($reservation->isReturnChangeExpired())
                        <p class="text-sm font-semibold text-[#8C1D2B]">El plazo para elegir tu nueva fecha de regreso venció. Tu regreso será anulado.</p>
                    @elseif ($reservation->isReturnChangePending())
                        <p class="text-sm font-semibold text-[#2B1113]">Solicitaste cambiar tu fecha de regreso.</p>
                        <p class="mt-1 text-xs text-[#2B1113]/70">Tienes hasta el <strong>{{ $reservation->return_change_deadline->format('d/m/Y H:i') }}</strong> para elegir una nueva fecha. Si no lo haces a tiempo, tu regreso se anula automáticamente.</p>
                        <a href="{{ route('cliente.boletos.return-change.choose', $reservation) }}" class="mt-3 inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                            Elegir nueva fecha de regreso
                        </a>
                    @elseif ($reservation->canRequestReturnChange())
                        <p class="text-sm font-semibold text-[#2B1113]">¿No vas a regresar en la fecha programada?</p>
                        <p class="mt-1 text-xs text-[#2B1113]/70">Puedes solicitar cambiar tu fecha de regreso. Tendrás 5 días hábiles para elegir una nueva fecha entre los viajes disponibles de esta misma ruta. El nuevo asiento <strong>no será el mismo</strong> y está <strong>sujeto a disponibilidad</strong>. No se cobra ni se regresa dinero.</p>
                        <form method="POST" action="{{ route('cliente.boletos.return-change.request', $reservation) }}" class="mt-3" onsubmit="return confirm('Tendrás 5 días hábiles para elegir tu nueva fecha de regreso. El nuevo asiento no será el mismo y está sujeto a disponibilidad. ¿Deseas continuar?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border-2 border-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-[#8C1D2B] hover:bg-[#8C1D2B] hover:text-white transition-colors">
                                No voy a regresar en la fecha programada
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- QR --}}
            <div class="px-7 pt-6 pb-7">
                <div class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-[#8C1D2B]/30 bg-[#FFFBF6] p-6 sm:flex-row sm:items-center">
                    <img src="{{ $qrUrl }}" alt="Código QR del boleto" class="h-44 w-44 shrink-0 rounded-lg mx-auto sm:mx-0">
                    <div class="text-center sm:text-left">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#8C1D2B]">Muestra este QR al abordar</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-[#2B1113]">El operador lo escanea para registrar tu {{ $legLabel }}. Puedes enseñar esta pantalla directamente o imprimir el boleto.</p>
                        <p class="mt-3 select-all break-all rounded-lg bg-white px-3 py-2 font-mono text-xs font-bold tracking-wide text-[#2B1113] ring-1 ring-black/10">{{ $reservation->ticket_code }}</p>
                        <p class="mt-1.5 text-[10px] text-[#2B1113]/50">Si el QR no escanea, dicta este código al operador.</p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-7 py-4 text-center text-[11px] leading-relaxed text-white" style="background:#2B1113;">
                <p class="font-semibold">MERLO Transportes &middot; Boleto digital</p>
                <p class="opacity-70">Este código QR es único para este viaje. No es transferible a otro viaje.</p>
            </div>
        </div>
    </main>
</body>
</html>
