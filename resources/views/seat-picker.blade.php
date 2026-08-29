<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Elegir asientos</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- OpenPay.js (sandbox-safe URL — same script in production) --}}
        <script src="https://openpay.s3.amazonaws.com/openpay.v1.min.js"></script>
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-black/5">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <a href="/" class="flex items-center gap-3 shrink-0">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-11 w-auto">
                    </a>
                    <a href="{{ route('travel.search', ['from' => $trip->from, 'to' => $trip->to]) }}" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                        Volver
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
            <div class="mb-8">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#8C1D2B]">Elegir asientos</p>
                <h1 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-[#2B1113]">
                    {{ $trip->from }} → {{ $trip->to }}
                </h1>
                <p class="mt-2 text-sm text-[#2B1113]/60">
                    {{ $trip->day ? $trip->day->format('d/m/Y') : 'Sin fecha' }} · {{ $trip->departure_time_formatted ?? 'Sin horario' }} · {{ $trip->formatted_price }}
                </p>
            </div>

            @if (session('error'))
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <p id="seat-picker-alert" class="hidden mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700"></p>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                {{-- ===================== Plano de asientos ===================== --}}
                <div class="lg:col-span-8 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                    @if ($trip->busUnit->has_upper_deck)
                        <div class="mb-4 inline-flex rounded-xl bg-[#FFFBF6] p-1 ring-1 ring-black/5">
                            <button type="button" id="deck-lower" class="deck-tab rounded-lg px-4 py-2 text-sm font-bold transition-colors">
                                Planta baja
                            </button>
                            <button type="button" id="deck-upper" class="deck-tab rounded-lg px-4 py-2 text-sm font-bold transition-colors">
                                Planta alta
                            </button>
                        </div>
                    @endif

                    <div class="mb-4 flex flex-wrap items-center gap-4 text-xs font-semibold text-[#2B1113]/60">
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#15803D] bg-[#22C55E]"></span> Disponible</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#A16207] bg-[#FACC15]"></span> Apartado</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#991B1B] bg-[#EF4444]"></span> Vendido</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-2 border-[#F5B301] bg-[#22C55E]"></span> VIP</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#475569] bg-[#94A3B8]"></span> Referencia (puerta, escaleras, etc.)</span>
                    </div>

                    <div id="seat-canvas" class="overflow-auto rounded-2xl border border-black/10 bg-[#FFFBF6]"></div>
                </div>

                {{-- ===================== Resumen / pagar ===================== --}}
                <div class="lg:col-span-4 lg:sticky lg:top-24 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                    <h2 class="font-[Poppins] text-lg font-bold text-[#2B1113]">Tu selección</h2>

                    @php
                        $oneWayPrice = $trip->priceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY);
                        $roundPrice = $trip->priceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP);
                        $hasSavedCards = $savedCards->isNotEmpty();
                    @endphp
                    <div class="mt-3 inline-flex rounded-xl bg-[#FFFBF6] p-1 ring-1 ring-black/5 w-full" id="trip-type-toggle">
                        <button type="button" data-trip-type="one_way" class="trip-type-tab flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $defaultTripType === \App\Models\TripTicketPrice::TYPE_ONE_WAY ? 'bg-[#8C1D2B] text-white' : 'text-[#2B1113]/60' }}">
                            <span class="block">Solo ida</span>
                            <span class="block text-[10px] font-semibold opacity-80">{{ $oneWayPrice ? $oneWayPrice->formatted_price : '—' }}</span>
                        </button>
                        <button type="button" data-trip-type="round_trip" class="trip-type-tab flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $defaultTripType === \App\Models\TripTicketPrice::TYPE_ROUND_TRIP ? 'bg-[#8C1D2B] text-white' : 'text-[#2B1113]/60' }}">
                            <span class="block">Viaje redondo</span>
                            <span class="block text-[10px] font-semibold opacity-80">{{ $roundPrice ? $roundPrice->formatted_price : '—' }}</span>
                        </button>
                    </div>

                    <div id="selected-seats-list" class="mt-4 flex flex-wrap gap-2">
                        <p class="text-xs text-[#2B1113]/40">Selecciona un asiento para verlo aquí.</p>
                    </div>

                    <p id="seat-countdown" class="hidden mt-4 text-xs font-semibold text-amber-700"></p>

                    <form method="POST" action="{{ route('travel.seats.store', $trip) }}" id="seat-form" class="mt-6 space-y-4 border-t border-black/5 pt-4">
                        @csrf
                        <input type="hidden" name="trip_type" id="trip-type-input" value="{{ $defaultTripType }}">

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-[#2B1113]/60">Asientos seleccionados</span>
                            <span id="seat-count" class="font-bold text-[#2B1113]">0</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-[#2B1113]">Subtotal</span>
                            <span id="seat-subtotal" class="font-[Poppins] text-xl font-extrabold text-[#8C1D2B]">$0.00</span>
                        </div>

                        {{-- ========== Método de pago ========== --}}
                        <div class="border-t border-black/5 pt-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">Método de pago</p>

                            <div class="mt-2 grid grid-cols-3 gap-2" id="payment-method-tabs">
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="card" class="peer sr-only" checked>
                                    <div class="rounded-xl border-2 border-black/10 bg-[#FFFBF6] p-2 text-center transition-all peer-checked:border-[#8C1D2B] peer-checked:bg-[#8C1D2B]/5 peer-checked:ring-2 peer-checked:ring-[#8C1D2B]/20">
                                        <div class="mx-auto mb-1 flex h-6 w-8 items-center justify-center rounded bg-white ring-1 ring-black/10">
                                            <div class="h-2 w-5 rounded-sm bg-gradient-to-r from-[#8C1D2B] to-[#F5B301]"></div>
                                        </div>
                                        <p class="text-[11px] font-bold text-[#2B1113]">Tarjeta</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="oxxo" class="peer sr-only">
                                    <div class="rounded-xl border-2 border-black/10 bg-[#FFFBF6] p-2 text-center transition-all peer-checked:border-[#8C1D2B] peer-checked:bg-[#8C1D2B]/5 peer-checked:ring-2 peer-checked:ring-[#8C1D2B]/20">
                                        <div class="mx-auto mb-1 flex h-6 w-8 items-center justify-center rounded bg-[#F5B301] text-[10px] font-extrabold text-[#2B1113]">O</div>
                                        <p class="text-[11px] font-bold text-[#2B1113]">OXXO</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="spei" class="peer sr-only">
                                    <div class="rounded-xl border-2 border-black/10 bg-[#FFFBF6] p-2 text-center transition-all peer-checked:border-[#8C1D2B] peer-checked:bg-[#8C1D2B]/5 peer-checked:ring-2 peer-checked:ring-[#8C1D2B]/20">
                                        <div class="mx-auto mb-1 flex h-6 w-8 items-center justify-center rounded bg-[#8C1D2B] text-[9px] font-extrabold text-white">SPEI</div>
                                        <p class="text-[11px] font-bold text-[#2B1113]">Transferencia</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- ========== Panel Tarjeta ========== --}}
                        <div data-payment-panel="card" class="space-y-3">
                            @if ($hasSavedCards)
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Tus tarjetas guardadas</p>
                                <div class="space-y-1.5">
                                    @foreach ($savedCards as $card)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-black/10 bg-white p-2.5 transition-colors hover:border-[#8C1D2B]/40 has-[:checked]:border-[#8C1D2B] has-[:checked]:bg-[#8C1D2B]/5 has-[:checked]:ring-2 has-[:checked]:ring-[#8C1D2B]/20">
                                            <input type="radio" name="use_saved_card" value="{{ $card->id }}" class="saved-card-radio h-4 w-4 text-[#8C1D2B] focus:ring-[#8C1D2B]" data-card-id="{{ $card->openpay_card_id }}" @checked($loop->first)>
                                            <div class="flex h-7 w-10 shrink-0 items-center justify-center rounded bg-white ring-1 ring-black/10">
                                                <span class="text-[9px] font-extrabold text-[#2B1113]">{{ strtoupper(substr($card->card_brand ?? '••', 0, 4)) }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-bold text-[#2B1113]">{{ $card->display_label }}</p>
                                                @if ($card->is_default)<p class="text-[10px] font-semibold text-[#8C1D2B]">Predeterminada</p>@endif
                                            </div>
                                        </label>
                                    @endforeach
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-black/15 bg-[#FFFBF6] p-2.5 transition-colors hover:border-[#8C1D2B]/40 has-[:checked]:border-[#8C1D2B] has-[:checked]:bg-[#8C1D2B]/5 has-[:checked]:ring-2 has-[:checked]:ring-[#8C1D2B]/20">
                                        <input type="radio" name="use_saved_card" value="0" class="saved-card-radio h-4 w-4 text-[#8C1D2B] focus:ring-[#8C1D2B]">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-bold text-[#2B1113]">Usar otra tarjeta</p>
                                            <p class="text-[10px] text-[#2B1113]/50">Ingresa los datos de una nueva tarjeta</p>
                                        </div>
                                    </label>
                                </div>
                            @endif

                            <div id="new-card-form" class="{{ $hasSavedCards ? 'hidden' : '' }} space-y-2.5">
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Nombre del titular</span>
                                    <input type="text" id="card-holder-name" autocomplete="cc-name" placeholder="Como aparece en la tarjeta" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Número de tarjeta</span>
                                    <input type="text" id="card-number" autocomplete="cc-number" inputmode="numeric" maxlength="19" placeholder="4111 1111 1111 1111" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 font-mono text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="block">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Mes</span>
                                        <input type="text" id="card-exp-month" autocomplete="cc-exp-month" inputmode="numeric" maxlength="2" placeholder="MM" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-center font-mono text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Año</span>
                                        <input type="text" id="card-exp-year" autocomplete="cc-exp-year" inputmode="numeric" maxlength="2" placeholder="AA" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-center font-mono text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">CVV</span>
                                        <input type="text" id="card-cvv" autocomplete="cc-csc" inputmode="numeric" maxlength="4" placeholder="123" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-center font-mono text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </label>
                                </div>

                                <label class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" name="save_card" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]">
                                    <span class="text-xs text-[#2B1113]/70">Guardar tarjeta para futuras compras</span>
                                </label>
                            </div>

                            <input type="hidden" name="openpay_token" id="openpay-token-input" value="">
                            <input type="hidden" name="device_session_id" id="device-session-id-input" value="">
                            <input type="hidden" name="saved_card_id" id="saved-card-id-input" value="{{ $hasSavedCards ? $savedCards->first()->id : '' }}">
                        </div>

                        {{-- ========== Panel OXXO ========== --}}
                        <div data-payment-panel="oxxo" class="hidden space-y-3">
                            <div class="rounded-2xl border border-[#F5B301]/40 bg-[#F5B301]/10 p-3">
                                <div class="flex items-start gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F5B301] text-xs font-extrabold text-[#2B1113]">O</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-[#2B1113]">Pago en OXXO</p>
                                        <p class="mt-0.5 text-[11px] text-[#2B1113]/70">Te enviaremos un código de barras. Llévalo a cualquier tienda OXXO antes de que venza (2 días).</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ========== Panel SPEI ========== --}}
                        <div data-payment-panel="spei" class="hidden space-y-3">
                            <div class="rounded-2xl border border-[#8C1D2B]/30 bg-[#8C1D2B]/5 p-3">
                                <div class="flex items-start gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#8C1D2B] text-[10px] font-extrabold text-white">SPEI</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-[#2B1113]">Transferencia SPEI</p>
                                        <p class="mt-0.5 text-[11px] text-[#2B1113]/70">Te enviaremos una CLABE. Transfiere el monto exacto desde tu banca en línea (vence en 1 día).</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ========== Datos del cliente (todos los métodos) ========== --}}
                        <div class="border-t border-black/5 pt-3 space-y-2.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Datos de contacto</p>
                            <label class="block">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Teléfono <span class="text-[#2B1113]/40">(para OXXO/SPEI)</span></span>
                                <input type="tel" name="customer_phone" id="customer-phone" value="{{ auth()->user()->phone ?? '' }}" autocomplete="tel" placeholder="+52 999 123 4567" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                            </label>
                        </div>

                        <p id="openpay-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"></p>

                        <button type="submit" id="seat-submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors disabled:opacity-40" disabled>
                            <span id="seat-submit-label">Pagar</span>
                            <svg id="seat-submit-spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </button>

                        <p class="flex items-center justify-center gap-1.5 text-[10px] text-[#2B1113]/40">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            Pago seguro procesado por OpenPay
                        </p>
                    </form>
                </div>
            </div>
        </main>

        <script>
            window.__SEAT_PICKER__ = {
                unitName: {!! json_encode($trip->busUnit->name) !!},
                landingRouteId: {{ $trip->id }},
                selfUserId: {{ auth()->id() }},
                pricePerSeat: {{ $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY) }},
                priceOneWay: {{ $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY) }},
                priceRoundTrip: {{ $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP) }},
                defaultTripType: {!! json_encode($defaultTripType) !!},
                canvasWidth: {{ $trip->busUnit->canvas_width }},
                canvasHeight: {{ $trip->busUnit->canvas_height }},
                hasUpperDeck: {{ $trip->busUnit->has_upper_deck ? 'true' : 'false' }},
                holdUrlBase: {!! json_encode(route('travel.seats.hold', [$trip, '__SEAT__'])) !!},
                seats: {!! json_encode($trip->busUnit->seats->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'kind' => $s->kind,
                    'type' => $s->type,
                    'deck' => $s->deck,
                    'shape' => $s->shape,
                    'width' => $s->width,
                    'height' => $s->height,
                    'corner_radius' => $s->corner_radius,
                    'border_width' => $s->border_width,
                    'color' => $s->color,
                    'allowed_trip_type' => $s->allowed_trip_type,
                    'pos_x' => $s->pos_x,
                    'pos_y' => $s->pos_y,
                ])) !!},
                takenIds: {!! json_encode($takenIds) !!},
                heldSeats: {!! json_encode($heldSeats->map(fn ($hold) => [
                    'bus_unit_seat_id' => $hold->bus_unit_seat_id,
                    'user_id' => $hold->user_id,
                    'expires_at' => $hold->expires_at->toIso8601String(),
                ])) !!},
            };

            window.__OPENPAY__ = {
                merchantId: {!! json_encode(config('services.openpay.id')) !!},
                publicKey: {!! json_encode(config('services.openpay.public_key')) !!},
                sandbox: {!! json_encode((bool) config('services.openpay.sandbox', true)) !!},
            };
        </script>
        @vite(['resources/js/seat-picker.js', 'resources/js/openpay-checkout.js'])
    </body>
</html>
