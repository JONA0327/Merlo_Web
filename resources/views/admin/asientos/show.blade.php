<x-admin-layout :active="'asientos'" :title="'Apartar asientos — '.$trip->from.' → '.$trip->to">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.asientos.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#8C1D2B] hover:text-[#6F1622]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17.5 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h11.19A.75.75 0 0117.5 10z" clip-rule="evenodd"/></svg>
                Volver a la lista de viajes
            </a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">{{ $trip->from }} → {{ $trip->to }}</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">
                {{ $trip->day ? $trip->day->format('d/m/Y') : 'Sin fecha' }} ·
                {{ $trip->departure_time_formatted ?? 'Sin horario' }} ·
                {{ $trip->duration }} ·
                {{ $trip->formatted_price }}
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold text-[#2B1113]/60">
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-amber-800">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                {{ $reservations->where('status', \App\Models\SeatReservation::STATUS_PENDING)->count() }} pendiente{{ $reservations->where('status', \App\Models\SeatReservation::STATUS_PENDING)->count() === 1 ? '' : 's' }}
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-blue-800">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                {{ $reservations->where('status', \App\Models\SeatReservation::STATUS_SENT)->count() }} enviado{{ $reservations->where('status', \App\Models\SeatReservation::STATUS_SENT)->count() === 1 ? '' : 's' }}
            </span>
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 xl:items-start">
        {{-- ===================== Plano de asientos ===================== --}}
        <div class="xl:col-span-7 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-4 text-[11px] font-semibold text-[#2B1113]/60">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#15803D] bg-white"></span> Disponible</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-2 border-[#16A34A] bg-white"></span> Seleccionado</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#A16207] bg-[#FACC15]"></span> Apartado (pendiente)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#1D4ED8] bg-[#3B82F6]"></span> Boleto enviado</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#991B1B] bg-[#EF4444]"></span> Vendido</span>
            </div>

            <div id="admin-seat-canvas" class="overflow-auto rounded-2xl border border-black/10 bg-[#FFFBF6]" style="min-height:560px;"></div>
        </div>

        {{-- ===================== Panel derecho: apartado + lista ===================== --}}
        <div class="xl:col-span-5 space-y-6">
            {{-- Form para crear apartado --}}
            <form method="POST" action="{{ route('admin.asientos.store', $trip) }}" id="apartado-form" class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                @csrf

                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Apartar para un cliente</h3>
                <p class="mt-1 text-xs text-[#2B1113]/60">Selecciona uno o varios asientos en el plano y captura los datos del cliente. Quedarán como <strong>pendientes</strong>.</p>

                @php
                    $oneWayPrice = $trip->priceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY);
                    $roundPrice = $trip->priceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP);
                @endphp
                <div class="mt-3 inline-flex rounded-xl bg-[#FFFBF6] p-1 ring-1 ring-black/5 w-full" id="admin-trip-type-toggle">
                    <button type="button" data-trip-type="one_way" class="admin-trip-type-tab flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors bg-[#8C1D2B] text-white">
                        <span class="block">Solo ida</span>
                        <span class="block text-[10px] font-semibold opacity-80">{{ $oneWayPrice ? $oneWayPrice->formatted_price : '—' }}</span>
                    </button>
                    <button type="button" data-trip-type="round_trip" class="admin-trip-type-tab flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors text-[#2B1113]/60">
                        <span class="block">Viaje redondo</span>
                        <span class="block text-[10px] font-semibold opacity-80">{{ $roundPrice ? $roundPrice->formatted_price : '—' }}</span>
                    </button>
                </div>
                <input type="hidden" name="trip_type" id="admin-trip-type-input" value="one_way">

                <div class="mt-4 space-y-3">
                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Nombre del cliente</span>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120" placeholder="Ej. María Hernández" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('customer_name') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Correo del cliente</span>
                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" required maxlength="180" placeholder="cliente@correo.com" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('customer_email') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Notas (opcional)</span>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Ej. Pagará en efectivo al abordar" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">{{ old('notes') }}</textarea>
                    </label>
                </div>

                <div id="apartado-selected-summary" class="mt-4 rounded-xl bg-[#FFFBF6] p-3 text-xs text-[#2B1113]/60">
                    Clic en el plano para seleccionar asientos.
                </div>

                <div id="apartado-hidden-inputs"></div>

                <button type="submit" id="apartado-submit" disabled class="mt-4 w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors disabled:cursor-not-allowed disabled:opacity-40">
                    Apartar selección
                </button>
            </form>

            {{-- Lista de apartados --}}
            <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Apartados</h3>

                @if ($reservations->isEmpty())
                    <p class="mt-3 text-xs text-[#2B1113]/50">Aún no hay apartados para este viaje.</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($reservations as $reservation)
                            <li class="rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $reservation->customer_display_name }}</p>
                                        <p class="mt-0.5 text-[11px] text-[#2B1113]/60 break-all">{{ $reservation->customer_display_email ?: '—' }}</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                            <span class="rounded-md bg-white px-2 py-0.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $reservation->seat?->label ?? '—' }}</span>
                                            <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#2B1113]/70 ring-1 ring-black/10">{{ $reservation->trip_type_label }}</span>
                                            <span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $reservation->isPending() ? 'bg-amber-200 text-amber-900' : ($reservation->isSent() ? 'bg-blue-200 text-blue-900' : 'bg-slate-200 text-slate-700') }}">
                                                {{ $reservation->status }}
                                            </span>
                                        </div>
                                        @if ($reservation->ticket_sent_at)
                                            <p class="mt-1.5 text-[10px] font-semibold text-[#2B1113]/50">Enviado {{ $reservation->ticket_sent_at->diffForHumans() }}</p>
                                        @endif
                                        @if ($reservation->notes)
                                            <p class="mt-1.5 text-[11px] italic text-[#2B1113]/60">{{ $reservation->notes }}</p>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                                        @if ($reservation->isPending())
                                            <form method="POST" action="{{ route('admin.asientos.send', [$trip, $reservation]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 transition-colors">
                                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                                    Enviar boleto
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.asientos.destroy', [$trip, $reservation]) }}" class="inline" onsubmit="return confirm('¿Cancelar este apartado? El asiento volverá a estar disponible.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-[10px] font-semibold text-red-600 hover:text-red-700">Cancelar</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <script>
        window.__ADMIN_SEAT_PICKER__ = {
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
            // Map of seat_id → status (pending | sent | null when free).
            // The admin can still pick "free" seats only — pending/sent
            // show as already taken.
            seatStatuses: {!! json_encode($reservationsBySeat->mapWithKeys(fn ($items, $seatId) => [
                $seatId => $items->last()->status,
            ])) !!},
            // The set of seats already taken by a real client purchase
            // (separate from admin apartados). Empty unless the customer
            // flow has been used in this environment.
            takenIds: {!! json_encode($takenIds) !!},
        };
    </script>
    @vite(['resources/js/admin-seat-picker.js'])
</x-admin-layout>
