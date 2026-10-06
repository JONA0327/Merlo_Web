<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Elegir asientos</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                        $regresoPrice = $trip->priceFor(\App\Models\TripTicketPrice::TYPE_REGRESO);
                        $showRegreso = $trip->return_date !== null && $regresoPrice;
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
                        @if ($resaleSeatIds->isNotEmpty())
                            <button type="button" data-trip-type="return_resale" class="trip-type-tab flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $defaultTripType === 'return_resale' ? 'bg-[#8C1D2B] text-white' : 'text-[#2B1113]/60' }}">
                                <span class="block">Regreso disponible</span>
                                <span class="block text-[10px] font-semibold opacity-80">{{ $oneWayPrice ? $oneWayPrice->formatted_price : '—' }}</span>
                            </button>
                        @endif
                        @if ($showRegreso)
                            <button type="button" data-trip-type="regreso" class="trip-type-tab flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $defaultTripType === 'regreso' ? 'bg-[#8C1D2B] text-white' : 'text-[#2B1113]/60' }}">
                                <span class="block">De regreso</span>
                                <span class="block text-[10px] font-semibold opacity-80">{{ $regresoPrice->formatted_price }}</span>
                            </button>
                        @endif
                    </div>
                    @if ($resaleSeatIds->isNotEmpty())
                        <p class="mt-2 text-[11px] text-[#2B1113]/50">Asientos liberados por pasajeros que no usarán su regreso. Disponibilidad limitada.</p>
                    @endif

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

                        {{-- ========== Selector de método de pago ==========
                             Solo Transferencia y Efectivo — los pagos con tarjeta / OXXO / SPEI
                             pasaron por OpenPay, que está desactivado. Transfer requiere
                             comprobante + validación del admin; Efectivo se paga y activa
                             directamente en ventanilla, sin QR hasta entonces. --}}
                            <div class="border-t border-black/5 pt-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">Método de pago</p>
                                <div class="mt-2 grid grid-cols-2 gap-2" id="payment-method-tabs">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="payment_method" value="transfer" class="peer sr-only" checked>
                                        <div class="rounded-xl border-2 border-black/10 bg-[#FFFBF6] p-2 text-center transition-all peer-checked:border-[#8C1D2B] peer-checked:bg-[#8C1D2B]/5 peer-checked:ring-2 peer-checked:ring-[#8C1D2B]/20">
                                            <div class="mx-auto mb-1 flex h-6 w-8 items-center justify-center rounded bg-[#8C1D2B] text-[9px] font-extrabold text-white">TRA</div>
                                            <p class="text-[11px] font-bold text-[#2B1113]">Transferencia</p>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="payment_method" value="cash" class="peer sr-only">
                                        <div class="rounded-xl border-2 border-black/10 bg-[#FFFBF6] p-2 text-center transition-all peer-checked:border-[#8C1D2B] peer-checked:bg-[#8C1D2B]/5 peer-checked:ring-2 peer-checked:ring-[#8C1D2B]/20">
                                            <div class="mx-auto mb-1 flex h-6 w-8 items-center justify-center rounded bg-[#F5B301] text-[10px] font-extrabold text-[#2B1113]">$</div>
                                            <p class="text-[11px] font-bold text-[#2B1113]">Efectivo</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div data-payment-panel="transfer" class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">Pago por transferencia bancaria</p>

                                @forelse ($paymentMethods as $method)
                                    <div class="rounded-2xl border border-[#8C1D2B]/20 bg-[#FFFBF6] p-3">
                                        <p class="text-xs font-bold text-[#2B1113]">{{ $method->label }}@if ($method->bank_name) · {{ $method->bank_name }}@endif</p>
                                        <p class="mt-1 text-[11px] text-[#2B1113]/70">Beneficiario: {{ $method->beneficiary_name }}</p>
                                        @if ($method->clabe)
                                            <p class="mt-1 select-all break-all font-mono text-xs font-bold text-[#2B1113]">CLABE: {{ $method->clabe }}</p>
                                        @endif
                                        @if ($method->card_number)
                                            <p class="mt-1 select-all break-all font-mono text-xs font-bold text-[#2B1113]">Tarjeta: {{ $method->card_number }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
                                        Por el momento no hay una cuenta configurada para recibir transferencias. Contáctanos para completar tu compra.
                                    </p>
                                @endforelse

                                <p class="text-[11px] text-[#2B1113]/60">
                                    Al confirmar, te daremos un <strong>número de referencia único</strong> que debes anotar como concepto de tu transferencia, y podrás subir tu comprobante. Tus asientos quedan apartados por 3 días mientras se valida el pago.
                                </p>
                            </div>

                            <div data-payment-panel="cash" class="hidden space-y-3">
                                <div class="rounded-2xl border border-[#F5B301]/40 bg-[#F5B301]/10 p-3">
                                    <div class="flex items-start gap-2.5">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F5B301] text-base font-extrabold text-[#2B1113]">$</div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-bold text-[#2B1113]">Pago en efectivo</p>
                                            <p class="mt-0.5 text-[11px] text-[#2B1113]/70">Apartas tus asientos ahora y pagas en ventanilla antes de tu viaje. Un operador confirmará el pago y te imprimirá tu boleto con código QR en el momento.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        {{-- ========== Datos del cliente (todos los métodos) ========== --}}
                        <div class="border-t border-black/5 pt-3 space-y-2.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Datos de contacto</p>

                            @guest
                                {{-- Guest checkout: there's no User record to fall back on, so
                                     name + phone must come from the form (controller marks them
                                     requiredIf(no-user)). When signed in, these stay optional
                                     because the controller falls back to $user->name / phone. --}}
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Nombre completo</span>
                                    <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120" autocomplete="name" placeholder="Como aparece en tu identificación" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    @error('customer_name') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                                </label>
                            @endguest

                            <label class="block">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Teléfono<span class="text-[#8C1D2B]"> *</span></span>
                                {{-- The * is always shown but only enforced server-side for
                                     guests (see SeatPickerController::store's Rule::requiredIf).
                                     Authenticated users without a phone on file still get the
                                     asterisk as a "please fill this if you want a confirmation
                                     by SMS" hint. --}}
                                <input type="tel" name="customer_phone" id="customer-phone" value="{{ auth()->user()->phone ?? old('customer_phone') }}" autocomplete="tel" placeholder="+52 999 123 4567" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                @error('customer_phone') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                            </label>
                        </div>


                        <button type="submit" id="seat-submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors disabled:opacity-40" disabled>
                            <span id="seat-submit-label">Reservar</span>
                            <svg id="seat-submit-spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </button>

                        
                    </form>
                </div>
            </div>
        </main>

        <script>
            window.__SEAT_PICKER__ = {
                unitName: {!! json_encode($trip->busUnit->name) !!},
                landingRouteId: {{ $trip->id }},
                // auth()->id() returns null when nobody's signed in — `{{ null }}` would
                // render an empty string and leave a dangling `selfUserId: ,` that
                // crashes the script. Forcing the literal `null` token keeps the
                // shape valid either way; seat-picker.js's canServerHold check
                // already treats null as "guest, no server-side hold".
                selfUserId: {{ auth()->id() !== null ? auth()->id() : 'null' }},
                // For comparing against active holds. Same shape as
                // SeatHold::holder_idAccessor (u:{id} for logged-in,
                // s:{session_id} for guests) so the JS can compare strings.
                selfHolderId: {!! json_encode($selfHolderId ?? '') !!},
                // Same null-trap as selfUserId above: a trip without a configured price
                // would otherwise render `priceOneWay: ,` and break the JS.
                pricePerSeat: {{ ($p = $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY)) !== null ? $p : 'null' }},
                priceOneWay: {{ ($p = $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY)) !== null ? $p : 'null' }},
                priceRoundTrip: {{ ($p = $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP)) !== null ? $p : 'null' }},
                priceRegreso: {{ ($p = $trip->numericPriceFor(\App\Models\TripTicketPrice::TYPE_REGRESO)) !== null ? $p : 'null' }},
                defaultTripType: {!! json_encode($defaultTripType) !!},
                resaleSeatIds: {!! json_encode($resaleSeatIds->values()) !!},
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
                heldSeats: {!! json_encode($heldSeats) !!},
            };

            // Submit-button "submitting" state / double-submit guard.
            document.getElementById('seat-form')?.addEventListener('submit', function () {
                const button = document.getElementById('seat-submit');
                const label = document.getElementById('seat-submit-label');
                const spinner = document.getElementById('seat-submit-spinner');
                if (button) button.disabled = true;
                if (label) label.textContent = 'Reservando…';
                spinner?.classList.remove('hidden');
            });
        </script>
        @vite(array_filter([
            'resources/js/seat-picker.js',]))
    </body>
</html>
