<x-admin-layout :active="'guias'" :title="'Guía — '.$guide->from.' → '.$guide->to">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.guias.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#8C1D2B] hover:text-[#6F1622]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17.5 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h11.19A.75.75 0 0117.5 10z" clip-rule="evenodd"/></svg>
                Volver a la lista de guías
            </a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">{{ $guide->from }} → {{ $guide->to }}</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">
                Plantilla: {{ $guide->busUnit->name }} ·
                {{ $guide->date_from->format('d/m/Y') }} – {{ $guide->date_to->format('d/m/Y') }}
            </p>
        </div>
        <a href="{{ route('admin.guias.edit', $guide) }}" class="text-xs font-semibold text-[#2B1113]/60 hover:text-[#2B1113]">Editar guía</a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6 rounded-3xl bg-white p-4 ring-1 ring-black/5 shadow-sm">
        <form method="GET" action="{{ route('admin.guias.show', $guide) }}" class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Fecha a apartar</span>
                <input type="date" name="fecha" value="{{ $date->format('Y-m-d') }}" min="{{ $guide->date_from->format('Y-m-d') }}" max="{{ $guide->date_to->format('Y-m-d') }}" class="mt-1 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
            </label>
            <button type="submit" class="rounded-xl bg-[#2B1113]/5 px-4 py-2 text-xs font-bold text-[#2B1113] hover:bg-[#2B1113]/10 transition-colors">Ver fecha</button>
            @if ($date->lt(\Illuminate\Support\Carbon::today()))
                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-700">Fecha pasada — solo lectura</span>
            @endif
        </form>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 xl:items-start">
        {{-- ===================== Plano de asientos ===================== --}}
        <div class="xl:col-span-7 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-4 text-[11px] font-semibold text-[#2B1113]/60">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#15803D] bg-white"></span> Disponible</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-2 border-[#16A34A] bg-white"></span> Seleccionado</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#A16207] bg-[#FACC15]"></span> Apartado en guía (para esta fecha)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#991B1B] bg-[#EF4444]"></span> Regreso agendado (del mismo viaje)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#6B21A8] bg-[#A855F7]"></span> Predeterminado (de planta)</span>
            </div>

            <div id="admin-seat-canvas" class="overflow-auto rounded-2xl border border-black/10 bg-[#FFFBF6]" style="min-height:560px;"></div>
        </div>

        {{-- ===================== Panel derecho: apartado + lista ===================== --}}
        <div class="xl:col-span-5 space-y-6">
            @if ($date->lt(\Illuminate\Support\Carbon::today()))
                <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Fecha pasada</h3>
                    <p class="mt-1 text-xs text-[#2B1113]/60">Elige una fecha dentro del rango de la guía que no haya pasado para poder apartar asientos.</p>
                </div>
            @else
            <form method="POST" action="{{ route('admin.guias.reservations.store', $guide) }}" id="apartado-form" class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                @csrf
                <input type="hidden" name="travel_date" value="{{ $date->format('Y-m-d') }}">

                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Apartar para el {{ $date->format('d/m/Y') }}</h3>
                <p class="mt-1 text-xs text-[#2B1113]/60">Selecciona uno o varios asientos en el plano. El precio y el envío del boleto se definen cuando se abra el viaje real para esta fecha.</p>

                @php
                    $tripTypeLabels = \App\Models\TripTicketPrice::tripTypes();
                    $zones = $guide->busUnit->seats->pluck('zone')->filter()->unique()->sort()->values();
                @endphp
                <label class="mt-3 block">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Categoría</span>
                    <select name="trip_type" id="admin-trip-type-select" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @foreach ($tripTypeLabels as $type => $label)
                            <option value="{{ $type }}" {{ $type === \App\Models\TripTicketPrice::TYPE_ONE_WAY ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div id="admin-zone-picker" class="mt-3 hidden">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Zonas disponibles</span>
                    <p class="mt-0.5 text-[10px] text-[#2B1113]/40">Clic en una zona para seleccionar sus asientos en el plano.</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($zones as $zone)
                            <button type="button" data-zone="{{ $zone }}" class="admin-zone-chip rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#8C1D2B] ring-1 ring-[#8C1D2B]/30 hover:bg-[#8C1D2B]/10 transition-colors">{{ $zone }}</button>
                        @endforeach
                        @if ($zones->isEmpty())
                            <span class="text-[11px] text-[#2B1113]/40">No hay zonas definidas en el editor de esta unidad.</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Nombre del cliente</span>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120" placeholder="Ej. María Hernández" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('customer_name') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">WhatsApp del cliente</span>
                        <input type="tel" inputmode="numeric" name="customer_phone" value="{{ old('customer_phone') }}" required maxlength="12" placeholder="444 123 4567" class="phone-mx-input mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        <p class="mt-1 text-[10px] text-[#2B1113]/40">El boleto se envía por WhatsApp hasta que el viaje se abra y este apartado se vincule.</p>
                        @error('customer_phone') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Correo del cliente (opcional)</span>
                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="180" placeholder="cliente@correo.com" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('customer_email') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">¿Pagado?</span>
                            <select name="paid" id="admin-paid-select" required class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="1" {{ old('paid', '1') === '1' ? 'selected' : '' }}>Sí, ya está pagado</option>
                                <option value="0" {{ old('paid') === '0' ? 'selected' : '' }}>No, pagará después</option>
                            </select>
                            @error('paid') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Método de pago (todos)</span>
                            {{-- Not submitted directly — quick default that fills the
                                 per-seat selects (payment_method[seat_id]), same shared
                                 mechanism Apartar asientos uses. --}}
                            <select id="admin-payment-method-select" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="transfer">Transferencia</option>
                                <option value="cash">Efectivo</option>
                                <option value="tbd">Por definir</option>
                            </select>
                            @error('payment_method') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Notas (opcional)</span>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Ej. Pagará en efectivo al abordar" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">{{ old('notes') }}</textarea>
                    </label>

                    <label class="flex items-center gap-2 rounded-xl bg-violet-50 p-3 ring-1 ring-violet-200">
                        <input type="checkbox" name="mark_as_standing" value="1" class="h-4 w-4 rounded border-violet-300 text-violet-700 focus:ring-violet-500">
                        <span class="text-xs font-semibold text-violet-900">Asiento(s) predeterminado(s) — se apartan solos en cada viaje de esta ruta a partir de ahora</span>
                    </label>
                </div>

                <div id="apartado-selected-summary" class="mt-4 rounded-xl bg-[#FFFBF6] p-3 text-xs text-[#2B1113]/60">
                    Clic en el plano para seleccionar asientos.
                </div>

                <div id="apartado-hidden-inputs"></div>

                <button type="submit" id="apartado-submit" disabled class="mt-4 w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors disabled:cursor-not-allowed disabled:opacity-40">
                    Apartar en la guía
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- ===================== Lista de apartados de la guía (todas las fechas) ===================== --}}
    <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Apartados pendientes en esta guía</h3>

        @if ($rootsByCustomer->isEmpty())
            <p class="mt-3 text-xs text-[#2B1113]/50">Aún no hay apartados pendientes en esta guía.</p>
        @else
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($rootsByCustomer as $customerName => $reservations)
                    <li class="rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                        <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $customerName }}</p>

                        <ul class="mt-2 space-y-2">
                            @foreach ($reservations as $reservation)
                                @php
                                    $allSeats = collect([$reservation->seat?->label])->merge($reservation->groupSeats->pluck('seat.label'))->filter();
                                    $members = collect([$reservation])->merge($reservation->groupSeats);
                                    $seatMismatch = $members->contains(fn ($m) => $m->hasSeatMismatch());
                                @endphp
                                <li class="rounded-xl bg-white p-2.5 ring-1 ring-black/5 @if($seatMismatch) ring-2 ring-amber-300 @endif">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[11px] font-semibold text-[#8C1D2B]">{{ $reservation->travel_date?->format('d/m/Y') }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                @foreach ($allSeats as $seatLabel)
                                                    <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $seatLabel }}</span>
                                                @endforeach
                                                <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#2B1113]/70 ring-1 ring-black/10">{{ $reservation->trip_type_label }}</span>
                                            </div>
                                            @if ($reservation->notes && ! str_starts_with((string) $reservation->notes, 'group:'))
                                                <p class="mt-1.5 text-[11px] italic text-[#2B1113]/60">{{ $reservation->notes }}</p>
                                            @endif
                                        </div>

                                        <form method="POST" action="{{ route('admin.guias.reservations.destroy', [$guide, $reservation]) }}" class="inline shrink-0" data-confirm-delete="Vas a BORRAR este apartado de guía.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-[10px] font-semibold text-red-600 hover:text-red-700">Borrar</button>
                                        </form>
                                    </div>

                                    @foreach ($members as $member)
                                        @if ($member->hasSeatMismatch())
                                            @php
                                                $takenForSlot = $takenSeatIdsByDateLeg->get($member->travel_date->toDateString().'|'.$member->leg, collect());
                                                $freeSeats = $currentBusSeats->reject(fn ($s) => $takenForSlot->contains($s->id));
                                            @endphp
                                            <div class="mt-2 rounded-lg bg-amber-50 p-2 ring-1 ring-amber-200">
                                                <p class="text-[10px] font-bold text-amber-800">⚠ El asiento {{ $member->seat?->label ?? '—' }} no existe en el autobús actual de la guía — elige uno disponible:</p>
                                                <form method="POST" action="{{ route('admin.guias.reservations.reassign-seat', [$guide, $member]) }}" class="mt-1.5 flex gap-1.5">
                                                    @csrf
                                                    <select name="bus_unit_seat_id" required class="flex-1 rounded-lg border border-amber-300 bg-white px-2 py-1 text-[11px] font-bold text-[#2B1113]">
                                                        <option value="">Selecciona un asiento…</option>
                                                        @foreach ($freeSeats as $seatOption)
                                                            <option value="{{ $seatOption->id }}">{{ $seatOption->label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="shrink-0 rounded-lg bg-amber-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-amber-700 transition-colors">Reasignar</button>
                                                </form>
                                            </div>
                                        @endif
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <script>
        window.__ADMIN_SEAT_PICKER__ = {
            tripEnded: {{ $date->lt(\Illuminate\Support\Carbon::today()) ? 'true' : 'false' }},
            canvasWidth: {{ $guide->busUnit->canvas_width }},
            canvasHeight: {{ $guide->busUnit->canvas_height }},
            hasUpperDeck: {{ $guide->busUnit->has_upper_deck ? 'true' : 'false' }},
            seats: {!! json_encode($guide->busUnit->seats->map(fn ($s) => [
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
                'zone' => $s->zone,
                'pos_x' => $s->pos_x,
                'pos_y' => $s->pos_y,
            ])) !!},
            seatStatuses: {!! json_encode($pendingReservationsForDate->map(fn ($r) => $r->status)) !!},
            // A return leg staged via SeatReservation::createReturnLegTicket()
            // lives on this SAME guide (not a reversed one — see
            // TripGuide::linkTrip()), flagged by carrying a
            // source_reservation_id. Painted red so it reads apart from a
            // normal forward-staged apartado at a glance.
            seatIsReturnLeg: {!! json_encode($pendingReservationsForDate->map(fn ($r) => $r->source_reservation_id !== null)) !!},
            takenIds: [],
            seatIsStanding: {!! json_encode($standingSeatIds->mapWithKeys(fn ($id) => [$id => true])) !!},
        };
    </script>
    @vite(['resources/js/admin-seat-picker.js'])
</x-admin-layout>
