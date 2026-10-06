<x-admin-layout :active="'guias'" :title="'Editar guía'">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Editar guía</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">{{ $guide->from }} → {{ $guide->to }}</p>
        </div>
        <a href="{{ route('admin.guias.show', $guide) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Volver</a>
    </div>

    <section class="max-w-xl rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.guias.update', $guide) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="from" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Origen</label>
                <select id="from" name="from" required class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled {{ old('from', $guide->from) ? '' : 'selected' }}>Selecciona el origen</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->name }}" {{ old('from', $guide->from) === $dest->name ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                    @if (! $destinations->contains(fn ($d) => $d->name === $guide->from) && $guide->from)
                        <option value="{{ $guide->from }}" selected>{{ $guide->from }} (legacy — crea este destino para editarlo)</option>
                    @endif
                </select>
                @error('from') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="to" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Destino</label>
                <select id="to" name="to" required class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <option value="" disabled {{ old('to', $guide->to) ? '' : 'selected' }}>Selecciona el destino</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->name }}" {{ old('to', $guide->to) === $dest->name ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                    @if (! $destinations->contains(fn ($d) => $d->name === $guide->to) && $guide->to)
                        <option value="{{ $guide->to }}" selected>{{ $guide->to }} (legacy — crea este destino para editarlo)</option>
                    @endif
                </select>
                @error('to') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="bus_unit_id" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Plantilla (unidad / mapa de asientos)</label>
                <select id="bus_unit_id" name="bus_unit_id" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @foreach ($busUnits as $unit)
                        <option value="{{ $unit->id }}" {{ old('bus_unit_id', $guide->bus_unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Si cambias la unidad, los asientos ya apartados se intentan mover al asiento con la misma etiqueta en la nueva unidad. Si alguno no existe ahí, te avisamos para que lo corrijas a mano.</p>
                @error('bus_unit_id') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="date_from" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $guide->date_from->format('Y-m-d')) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('date_from') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="date_to" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Hasta</label>
                    <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $guide->date_to->format('Y-m-d')) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('date_to') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="notes" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Notas (opcional)</label>
                <textarea id="notes" name="notes" rows="2" maxlength="1000" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">{{ old('notes', $guide->notes) }}</textarea>
            </div>

            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                Guardar cambios
            </button>
        </form>
    </section>
</x-admin-layout>
