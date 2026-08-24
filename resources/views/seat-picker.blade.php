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

                    <div id="selected-seats-list" class="mt-4 flex flex-wrap gap-2">
                        <p class="text-xs text-[#2B1113]/40">Selecciona un asiento para verlo aquí.</p>
                    </div>

                    <p id="seat-countdown" class="hidden mt-4 text-xs font-semibold text-amber-700"></p>

                    <form method="POST" action="{{ route('travel.seats.store', $trip) }}" id="seat-form" class="mt-6 border-t border-black/5 pt-4">
                        @csrf

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-[#2B1113]/60">Asientos seleccionados</span>
                            <span id="seat-count" class="font-bold text-[#2B1113]">0</span>
                        </div>

                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-sm font-semibold text-[#2B1113]">Subtotal</span>
                            <span id="seat-subtotal" class="font-[Poppins] text-xl font-extrabold text-[#8C1D2B]">$0.00</span>
                        </div>

                        <button type="submit" id="seat-submit" class="mt-6 w-full inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white hover:bg-[#6F1622] transition-colors disabled:opacity-40" disabled>
                            Pagar
                        </button>
                    </form>
                </div>
            </div>
        </main>

        <script>
            window.__SEAT_PICKER__ = {
                unitName: {!! json_encode($trip->busUnit->name) !!},
                landingRouteId: {{ $trip->id }},
                selfUserId: {{ auth()->id() }},
                pricePerSeat: {{ $trip->numeric_price }},
                canvasWidth: {{ $trip->busUnit->canvas_width }},
                canvasHeight: {{ $trip->busUnit->canvas_height }},
                backgroundImageUrl: {!! json_encode($trip->busUnit->background_image_url) !!},
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
        </script>
        @vite(['resources/js/seat-picker.js'])
    </body>
</html>
