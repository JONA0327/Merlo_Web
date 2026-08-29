<x-admin-layout :active="'asientos'" :title="'Disponibilidad — '.$trip->from.' → '.$trip->to">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.asientos.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#8C1D2B] hover:text-[#6F1622]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17.5 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h11.19A.75.75 0 0117.5 10z" clip-rule="evenodd"/></svg>
                Volver a la lista de viajes
            </a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Disponibilidad de asientos · {{ $trip->from }} → {{ $trip->to }}</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">
                Define qué tipo de boleto puede comprar el cliente en cada asiento. Selecciona un modo y haz clic en los asientos del plano. Los cambios se guardan al pulsar <strong>Guardar disponibilidad</strong>.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Paint mode toolbar: three big buttons. The active one is filled,
         the others are outlined. Clicking a seat on the canvas assigns
         it to the current mode. --}}
    <div class="rounded-3xl bg-white p-4 ring-1 ring-black/5 shadow-sm">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <button type="button" data-paint-mode="one_way" class="paint-mode-btn group flex items-center justify-between gap-3 rounded-2xl border-2 border-[#3B82F6] bg-[#3B82F6] px-4 py-3 text-left text-white shadow-sm transition-all">
                <div>
                    <p class="font-[Poppins] text-sm font-extrabold">Solo ida</p>
                    <p class="text-[10px] font-semibold opacity-90">Marca asientos exclusivos para boletos sencillos.</p>
                </div>
                <span class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md bg-white/20 px-2 text-xs font-bold" id="stat-one-way">{{ $stats['one_way'] }}</span>
            </button>

            <button type="button" data-paint-mode="round_trip" class="paint-mode-btn group flex items-center justify-between gap-3 rounded-2xl border-2 border-[#F5B301] bg-white px-4 py-3 text-left text-[#2B1113] shadow-sm transition-all">
                <div>
                    <p class="font-[Poppins] text-sm font-extrabold">Viaje redondo</p>
                    <p class="text-[10px] font-semibold opacity-70">Marca asientos exclusivos para boletos redondos.</p>
                </div>
                <span class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md bg-black/5 px-2 text-xs font-bold" id="stat-round-trip">{{ $stats['round_trip'] }}</span>
            </button>

            <button type="button" data-paint-mode="both" class="paint-mode-btn group flex items-center justify-between gap-3 rounded-2xl border-2 border-[#15803D] bg-white px-4 py-3 text-left text-[#2B1113] shadow-sm transition-all">
                <div>
                    <p class="font-[Poppins] text-sm font-extrabold">Ambos</p>
                    <p class="text-[10px] font-semibold opacity-70">Restablece el asiento para que sirva en cualquier boleto.</p>
                </div>
                <span class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md bg-black/5 px-2 text-xs font-bold" id="stat-both">{{ $stats['both'] }}</span>
            </button>
        </div>

        <p class="mt-3 text-[11px] text-[#2B1113]/50">
            <strong>Tip:</strong> Shift+clic permite pintar varios asientos arrastrando. Los contadores arriba se actualizan en tiempo real según pintas.
        </p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-12 xl:items-start">
        {{-- Seat map --}}
        <div class="xl:col-span-8 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-4 text-[11px] font-semibold text-[#2B1113]/60">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#15803D] bg-white"></span> Ambos</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#1D4ED8] bg-[#3B82F6]"></span> Solo ida</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-2 border-[#A16207] bg-[#FACC15]"></span> Solo redondo</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#94A3B8] bg-[#CBD5E1]"></span> No es un asiento (puerta, contorno, etc.)</span>
            </div>

            <div id="availability-canvas" class="w-full overflow-auto rounded-2xl border border-black/10 bg-[#FFFBF6]" style="min-height:560px;"></div>
        </div>

        {{-- Pending changes panel --}}
        <div class="xl:col-span-4 space-y-4">
            <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Cambios sin guardar</h3>
                <p id="changes-empty" class="mt-3 text-xs text-[#2B1113]/50">No has pintado nada todavía. Selecciona un modo arriba y haz clic en los asientos del plano.</p>
                <ul id="changes-list" class="mt-3 space-y-1.5 text-xs"></ul>

                <form id="availability-form" method="POST" action="{{ route('admin.asientos.availability.update', $trip) }}" class="mt-4 space-y-2">
                    @csrf
                    @method('PUT')
                    <div id="changes-inputs"></div>
                    <button type="submit" id="availability-submit" disabled class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors disabled:cursor-not-allowed disabled:opacity-40">
                        Guardar disponibilidad
                    </button>
                    <a href="{{ route('admin.asientos.show', $trip) }}" class="block w-full text-center text-[11px] font-semibold text-[#2B1113]/60 hover:text-[#2B1113]">
                        Ir a apartar para un cliente
                    </a>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.__ADMIN_SEAT_AVAILABILITY__ = {
            tripId: {{ $trip->id }},
            unitName: {!! json_encode($trip->busUnit->name) !!},
            canvasWidth: {{ $trip->busUnit->canvas_width }},
            canvasHeight: {{ $trip->busUnit->canvas_height }},
            hasUpperDeck: {{ $trip->busUnit->has_upper_deck ? 'true' : 'false' }},
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
        };
    </script>
    @vite(['resources/js/admin-seat-availability.js'])
</x-admin-layout>
