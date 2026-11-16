<x-admin-layout :active="'guias'" :title="'Nueva guía'">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Nueva guía</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Define la ruta, la unidad (plantilla) y el rango de fechas en que vas a apartar asientos antes de que el viaje exista.</p>
        </div>
        <a href="{{ route('admin.guias.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Volver</a>
    </div>

    <section class="max-w-xl rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.guias.store') }}" class="space-y-4" x-data="{ mode: '{{ old('schedule_mode', 'date') === 'weekday' ? 'weekday' : 'date' }}' }">
            @csrf

            <div>
                <label for="from" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Origen</label>
                <select id="from" name="from" required class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled {{ old('from') ? '' : 'selected' }}>Selecciona el origen</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->name }}" {{ old('from') === $dest->name ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                </select>
                @error('from') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="to" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Destino</label>
                <select id="to" name="to" required class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled {{ old('to') ? '' : 'selected' }}>Selecciona el destino</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->name }}" {{ old('to') === $dest->name ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                </select>
                @error('to') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="bus_unit_id" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Plantilla (unidad / mapa de asientos)</label>
                <select id="bus_unit_id" name="bus_unit_id" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    <option value="">Selecciona una unidad</option>
                    @foreach ($busUnits as $unit)
                        <option value="{{ $unit->id }}" {{ old('bus_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Cuando se abra el viaje real, debe usar esta misma unidad para que los asientos se vinculen automáticamente.</p>
                @error('bus_unit_id') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Fechas específicas o recurrente (todos los Lun, Mié…) --}}
            <div>
                <span class="mb-1.5 block text-sm font-semibold text-[#2B1113]">¿Cuándo?</span>
                <div class="grid grid-cols-2 gap-2 rounded-xl bg-[#2B1113]/5 p-1">
                    <label class="cursor-pointer rounded-lg px-3 py-2 text-center text-sm font-bold transition-colors" :class="mode === 'date' ? 'bg-white text-[#8C1D2B] shadow-sm' : 'text-[#2B1113]/60'">
                        <input type="radio" name="schedule_mode" value="date" x-model="mode" class="sr-only"> Fecha(s)
                    </label>
                    <label class="cursor-pointer rounded-lg px-3 py-2 text-center text-sm font-bold transition-colors" :class="mode === 'weekday' ? 'bg-white text-[#8C1D2B] shadow-sm' : 'text-[#2B1113]/60'">
                        <input type="radio" name="schedule_mode" value="weekday" x-model="mode" class="sr-only"> Recurrente
                    </label>
                </div>
            </div>

            <div x-show="mode === 'weekday'" x-cloak>
                <span class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Días de la semana</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $i => $dayName)
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#FFFBF6] px-3 py-2 text-sm font-semibold text-[#2B1113] ring-1 ring-black/10">
                            <input type="checkbox" name="recurring_weekdays[]" value="{{ $i }}" class="h-4 w-4 rounded text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ in_array($i, old('recurring_weekdays', [])) ? 'checked' : '' }}>
                            {{ $dayName }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Solo aparece (y se puede apartar) esos días de la semana.</p>
                @error('recurring_weekdays') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="date_from" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ old('date_from') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" :required="mode !== 'weekday'">
                    @error('date_from') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="date_to" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Hasta <span class="font-normal text-[#2B1113]/40">(opcional)</span></label>
                    <input id="date_to" name="date_to" type="date" value="{{ old('date_to') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @error('date_to') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="-mt-2 text-xs text-[#2B1113]/50" x-show="mode === 'date'">Si solo eliges "Desde", la guía es de ese mismo día.</p>
            <p class="-mt-2 text-xs text-[#2B1113]/50" x-show="mode === 'weekday'" x-cloak>Sin "Desde" empieza hoy; sin "Hasta" se mantiene corriendo semana tras semana.</p>

            <div>
                <label for="notes" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Notas (opcional)</label>
                <textarea id="notes" name="notes" rows="2" maxlength="1000" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                Crear guía
            </button>
        </form>
    </section>
</x-admin-layout>
