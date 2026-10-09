<x-admin-layout active="planta" title="De planta">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Asientos de planta</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Un asiento fijo para la misma persona en una ruta — se asigna solo cada vez que se abra un viaje de esa ruta (misma unidad), sin tener que registrarla de nuevo. Queda pendiente de pago como cualquier apartado; es solo para no reescribir a clientes recurrentes.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Agregar asiento de planta</h3>
        <form method="POST" action="{{ route('admin.planta.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @csrf
            <div>
                <x-input-label for="planta-from" value="Origen" />
                <select id="planta-from" name="from" required class="mt-1.5 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled {{ old('from') ? '' : 'selected' }}>Origen…</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->name }}" {{ old('from') === $dest->name ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('from')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-to" value="Destino" />
                <select id="planta-to" name="to" required class="mt-1.5 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled {{ old('to') ? '' : 'selected' }}>Destino…</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->name }}" {{ old('to') === $dest->name ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('to')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-bus-unit" value="Unidad" />
                <select id="planta-bus-unit" name="bus_unit_id" required class="mt-1.5 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled selected>Unidad…</option>
                    @foreach ($busUnits as $unit)
                        <option value="{{ $unit->id }}" {{ (string) old('bus_unit_id') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('bus_unit_id')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-seat" value="Asiento" />
                <select id="planta-seat" name="bus_unit_seat_id" required class="mt-1.5 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" selected>Elige primero la unidad…</option>
                </select>
                <x-input-error :messages="$errors->get('bus_unit_seat_id')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-trip-type" value="Categoría" />
                <select id="planta-trip-type" name="trip_type" required class="mt-1.5 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @foreach ($tripTypeLabels as $type => $label)
                        <option value="{{ $type }}" {{ old('trip_type', 'one_way') === $type ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('trip_type')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-name" value="Nombre del cliente" />
                <x-text-input id="planta-name" name="customer_name" type="text" required class="block mt-1.5 w-full" value="{{ old('customer_name') }}" />
                <x-input-error :messages="$errors->get('customer_name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-phone" value="Teléfono" />
                <x-text-input id="planta-phone" name="customer_phone" type="text" required class="block mt-1.5 w-full" value="{{ old('customer_phone') }}" />
                <x-input-error :messages="$errors->get('customer_phone')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="planta-email" value="Correo (opcional)" />
                <x-text-input id="planta-email" name="customer_email" type="email" class="block mt-1.5 w-full" value="{{ old('customer_email') }}" />
                <x-input-error :messages="$errors->get('customer_email')" class="mt-1" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="planta-notes" value="Notas (opcional)" />
                <x-text-input id="planta-notes" name="notes" type="text" class="block mt-1.5 w-full" value="{{ old('notes') }}" placeholder="Ej. Empleado de la empresa X" />
            </div>
            <div class="flex items-end">
                <x-primary-button type="submit" class="w-full justify-center">Agregar</x-primary-button>
            </div>
        </form>
    </div>

    <div class="rounded-3xl bg-white ring-1 ring-black/5 shadow-sm overflow-hidden">
        @if ($groups->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm text-[#2B1113]/60">Aún no hay asientos de planta configurados.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-black/5 text-sm">
                <thead class="bg-[#FFFBF6] text-left text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">
                    <tr>
                        <th class="px-4 py-3">Ruta</th>
                        <th class="px-4 py-3">Unidad / Asientos</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Activo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 bg-white">
                    @foreach ($groups as $i => $g)
                        <tr class="hover:bg-[#FFFBF6]/60 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $g->from }} &rarr; {{ $g->to }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-[#2B1113]/70">
                                {{ $g->busUnit->name ?? '—' }}
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($g->seats as $label)
                                        <span class="rounded-md bg-[#FFFBF6] px-1.5 py-0.5 text-[10px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $label }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#2B1113]">{{ $g->customer_name }}</p>
                                <p class="text-[11px] text-[#2B1113]/60">{{ $g->customer_phone }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $tripTypeLabels[$g->trip_type] ?? $g->trip_type }}</td>
                            <td class="px-4 py-3">
                                @if ($g->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">Activo</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">Pausado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" class="planta-edit-toggle rounded-lg bg-[#FFFBF6] px-2.5 py-1 text-[11px] font-bold text-[#8C1D2B] ring-1 ring-black/10 hover:bg-[#8C1D2B]/5" data-target="planta-edit-{{ $i }}">Editar</button>
                                    <form method="POST" action="{{ route('admin.planta.destroy-many') }}" onsubmit="return confirmDeletePlanta()">
                                        @csrf
                                        @method('DELETE')
                                        @foreach ($g->ids as $id)
                                            <input type="hidden" name="ids[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="rounded-lg bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="planta-edit-{{ $i }}" class="hidden">
                            <td colspan="6" class="bg-[#FFFBF6] px-4 py-3">
                                <form method="POST" action="{{ route('admin.planta.update-many') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-6 sm:items-end">
                                    @csrf
                                    @method('PUT')
                                    @foreach ($g->ids as $id)
                                        <input type="hidden" name="ids[]" value="{{ $id }}">
                                    @endforeach
                                    <x-text-input name="customer_name" type="text" required class="block w-full" value="{{ $g->customer_name }}" />
                                    <x-text-input name="customer_phone" type="text" required class="block w-full" value="{{ $g->customer_phone }}" />
                                    <x-text-input name="customer_email" type="email" class="block w-full" value="{{ $g->customer_email }}" />
                                    <select name="trip_type" class="w-full rounded-lg border border-black/10 bg-white px-2 py-2 text-xs font-bold text-[#2B1113]">
                                        @foreach ($tripTypeLabels as $type => $label)
                                            <option value="{{ $type }}" {{ $g->trip_type === $type ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <label class="flex items-center gap-1.5 text-xs font-semibold text-[#2B1113]">
                                        <input type="checkbox" name="is_active" value="1" {{ $g->is_active ? 'checked' : '' }}>
                                        Activo
                                    </label>
                                    <x-primary-button type="submit" class="justify-center">Guardar (todos los asientos)</x-primary-button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        const PLANTA_SEATS_BY_UNIT = {!! json_encode($busUnits->mapWithKeys(fn ($unit) => [
            $unit->id => $unit->seats->filter(fn ($s) => $s->kind === 'seat' && $s->type !== 'disabled')->sortBy('label', SORT_NATURAL)->values()->map(fn ($s) => ['id' => $s->id, 'label' => $s->label]),
        ])) !!};

        const plantaBusUnitSelect = document.getElementById('planta-bus-unit');
        const plantaSeatSelect = document.getElementById('planta-seat');

        function refreshPlantaSeats() {
            const seats = PLANTA_SEATS_BY_UNIT[plantaBusUnitSelect.value] || [];
            plantaSeatSelect.innerHTML = seats.length
                ? seats.map((s) => `<option value="${s.id}">${s.label}</option>`).join('')
                : '<option value="" selected>Sin asientos disponibles</option>';
        }

        plantaBusUnitSelect?.addEventListener('change', refreshPlantaSeats);
        if (plantaBusUnitSelect?.value) refreshPlantaSeats();

        function confirmDeletePlanta() {
            const typed = window.prompt('Vas a BORRAR este asiento de planta. Ya no se asignará solo en los próximos viajes.\n\nEscribe ELIMINAR para confirmar:');
            return typed !== null && typed.trim().toUpperCase() === 'ELIMINAR';
        }

        document.querySelectorAll('.planta-edit-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.getAttribute('data-target'))?.classList.toggle('hidden');
            });
        });
    </script>
</x-admin-layout>
