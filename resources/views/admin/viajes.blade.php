<x-admin-layout active="viajes" title="Viajes">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Viajes</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Administra las rutas que se muestran en la landing y en las búsquedas del sitio.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_1.8fr]">
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Agregar viaje</h3>

            <form method="POST" action="{{ route('admin.viajes.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="from" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Origen</label>
                    <input id="from" name="from" type="text" value="{{ old('from') }}" placeholder="Ciudad de México" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('from')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="to" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Destino</label>
                    <input id="to" name="to" type="text" value="{{ old('to') }}" placeholder="Guadalajara" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('to')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="day" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Día del viaje</label>
                        <input id="day" name="day" type="date" value="{{ old('day') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('day')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="return_date" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Fecha de regreso</label>
                        <input id="return_date" name="return_date" type="date" value="{{ old('return_date') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('return_date')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="duration" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Duración</label>
                    <input id="duration" name="duration" type="text" value="{{ old('duration') }}" placeholder="6h 30m" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('duration')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="departure_time" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Horario</label>
                        <input id="departure_time" name="departure_time" type="time" value="{{ old('departure_time') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('departure_time')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="available_seats" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Asientos disponibles</label>
                        <input id="available_seats" name="available_seats" type="number" min="0" value="{{ old('available_seats', 1) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none read-only:bg-black/5 read-only:text-[#2B1113]/60">
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
                            <option value="{{ $unit->id }}" data-bookable="{{ $unit->bookable_seats_count }}" {{ old('bus_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->bookable_seats_count }} asientos)</option>
                        @endforeach
                    </select>
                    @error('bus_unit_id')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="ticket_price" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Costo del boleto</label>
                        <input id="ticket_price" name="ticket_price" type="text" value="{{ old('ticket_price') }}" placeholder="$650" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('ticket_price')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Orden</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                    Guardar viaje
                </button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Viajes actuales</h3>

            @if ($routes->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                    Aún no hay viajes registrados.
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($routes as $route)
                        <div class="rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <p class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $route->from }} → {{ $route->to }}</p>
                                    <p class="mt-1 text-sm text-[#2B1113]/60">{{ $route->day ? $route->day->format('d/m/Y') : 'Sin fecha' }} · {{ $route->departure_time_formatted ?? 'Horario no definido' }} · {{ $route->duration }}</p>
                                    <p class="mt-0.5 text-sm text-[#2B1113]/60">Regreso: {{ $route->return_date ? $route->return_date->format('d/m/Y') : 'Sin fecha' }}</p>
                                    <p class="mt-0.5 text-sm text-[#2B1113]/60">{{ $route->formatted_price }} · Asientos: {{ $route->available_seats ?? 0 }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $route->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $route->is_active ? 'Visible' : 'Oculta' }}
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $route->featured ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $route->featured ? '⭐ Destacado' : 'No destacado' }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center gap-2">
                                <a href="{{ route('admin.viajes.edit', $route) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                                    Editar
                                </a>
                                <form method="POST" action="{{ route('admin.viajes.toggle-featured', $route) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-sm font-semibold {{ $route->featured ? 'text-amber-600 hover:text-amber-700' : 'text-gray-600 hover:text-gray-700' }}">
                                        {{ $route->featured ? 'Quitar destaque' : 'Destacar' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.viajes.destroy', $route) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700" onclick="return confirm('¿Deseas eliminar este viaje?')">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
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
