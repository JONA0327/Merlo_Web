<x-admin-layout active="viajes" title="Editar Viaje">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Editar Viaje</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Actualiza los datos del viaje {{ $route->from }} → {{ $route->to }}</p>
        </div>
        <a href="{{ route('admin.viajes') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
            Volver
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_1fr]">
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <form method="POST" action="{{ route('admin.viajes.update', $route) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="from" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Origen</label>
                    <input id="from" name="from" type="text" value="{{ old('from', $route->from) }}" placeholder="Ciudad de México" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('from')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="to" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Destino</label>
                    <input id="to" name="to" type="text" value="{{ old('to', $route->to) }}" placeholder="Guadalajara" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('to')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="day" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Día del viaje</label>
                        <input id="day" name="day" type="date" value="{{ old('day', $route->day?->format('Y-m-d')) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('day')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="return_date" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Fecha de regreso</label>
                        <input id="return_date" name="return_date" type="date" value="{{ old('return_date', $route->return_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('return_date')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="duration" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Duración</label>
                    <input id="duration" name="duration" type="text" value="{{ old('duration', $route->duration) }}" placeholder="6h 30m" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('duration')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="departure_time" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Horario</label>
                        <input id="departure_time" name="departure_time" type="time" value="{{ old('departure_time', $route->departure_time) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('departure_time')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="available_seats" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Asientos disponibles</label>
                        <input id="available_seats" name="available_seats" type="number" min="0" value="{{ old('available_seats', $route->available_seats) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none read-only:bg-black/5 read-only:text-[#2B1113]/60">
                        <p id="available-seats-hint" class="mt-1 hidden text-xs text-[#2B1113]/50">Se calcula automáticamente según el mapa de asientos de la unidad.</p>
                        @error('available_seats')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="bus_unit_id" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Unidad / mapa de asientos</label>
                    <select id="bus_unit_id" name="bus_unit_id" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        <option value="">Sin unidad (usar contador simple)</option>
                        @foreach ($busUnits as $unit)
                            <option value="{{ $unit->id }}" data-bookable="{{ $unit->bookable_seats_count }}" {{ old('bus_unit_id', $route->bus_unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->bookable_seats_count }} asientos)</option>
                        @endforeach
                    </select>
                    @error('bus_unit_id')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-[#8C1D2B]/20 bg-[#8C1D2B]/5 p-3 sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#2B1113]/60">Precios del viaje</p>
                        <div class="mt-2 flex flex-wrap items-baseline gap-x-6 gap-y-1">
                            <div>
                                <span class="text-[10px] font-semibold text-[#2B1113]/40">Solo ida:</span>
                                <span class="ml-1 font-[Poppins] text-base font-extrabold text-[#8C1D2B]">{{ $route->formattedPriceFor(\App\Models\TripTicketPrice::TYPE_ONE_WAY) ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-semibold text-[#2B1113]/40">Redondo:</span>
                                <span class="ml-1 font-[Poppins] text-base font-extrabold text-[#8C1D2B]">{{ $route->formattedPriceFor(\App\Models\TripTicketPrice::TYPE_ROUND_TRIP) ?? '—' }}</span>
                            </div>
                        </div>
                        <a href="{{ route('admin.precios.index') }}" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-[#8C1D2B] hover:text-[#6F1622]">
                            Editar precios
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 10a.75.75 0 01.75-.75h11.19l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.25A.75.75 0 012.5 10z" clip-rule="evenodd"/></svg>
                        </a>
                    </div>

                    <div>
                        <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Orden</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $route->sort_order) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex items-end">
                        <label class="flex w-full items-center justify-between rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-semibold text-[#2B1113]">
                            <span>Visible</span>
                            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $route->is_active ? 'checked' : '' }}>
                        </label>
                    </div>

                    <div class="flex items-end">
                        <label class="flex w-full items-center justify-between rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-semibold text-[#2B1113]">
                            <span>Destacado</span>
                            <input type="checkbox" name="featured" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $route->featured ? 'checked' : '' }}>
                        </label>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                    Guardar cambios
                </button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113] mb-6">Información actual</h3>
            <div class="space-y-4 text-sm">
                <div>
                    <p class="font-semibold text-[#2B1113]">Ruta</p>
                    <p class="text-[#2B1113]/60">{{ $route->from }} → {{ $route->to }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Fecha</p>
                    <p class="text-[#2B1113]/60">{{ $route->day ? $route->day->format('d/m/Y') : 'Sin fecha' }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Fecha de regreso</p>
                    <p class="text-[#2B1113]/60">{{ $route->return_date ? $route->return_date->format('d/m/Y') : 'Sin fecha' }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Horario</p>
                    <p class="text-[#2B1113]/60">{{ $route->departure_time_formatted ?? 'Sin horario' }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Duración</p>
                    <p class="text-[#2B1113]/60">{{ $route->duration }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Asientos disponibles</p>
                    <p class="text-[#2B1113]/60">{{ $route->available_seats ?? 0 }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Costo</p>
                    <p class="text-[#2B1113]/60">{{ $route->formatted_price }}</p>
                </div>
                <div>
                    <p class="font-semibold text-[#2B1113]">Estado</p>
                    <div class="flex gap-2 mt-1">
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $route->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                            {{ $route->is_active ? 'Visible' : 'Oculta' }}
                        </span>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $route->featured ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $route->featured ? '⭐ Destacado' : 'No destacado' }}
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const unitSelect = document.getElementById('bus_unit_id');
            const seatsInput = document.getElementById('available_seats');
            const hint = document.getElementById('available-seats-hint');
            if (!unitSelect || !seatsInput) return;

            function syncAvailableSeats() {
                const option = unitSelect.options[unitSelect.selectedIndex];
                const bookable = option ? option.dataset.bookable : undefined;

                if (unitSelect.value && bookable !== undefined) {
                    seatsInput.value = bookable;
                    seatsInput.readOnly = true;
                    hint?.classList.remove('hidden');
                } else {
                    seatsInput.readOnly = false;
                    hint?.classList.add('hidden');
                }
            }

            unitSelect.addEventListener('change', syncAvailableSeats);
            syncAvailableSeats();
        })();
    </script>
</x-admin-layout>
