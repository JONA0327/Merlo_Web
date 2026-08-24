<x-admin-layout active="unidades" title="Distribución de Asientos">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Distribución de Asientos</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Define el mapa de asientos de cada unidad y asígnala a los viajes que la usan.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_1.8fr]">
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Nueva unidad</h3>

            <form method="POST" action="{{ route('admin.unidades.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Nombre</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Autobús 1" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('name')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Descripción</label>
                    <input id="description" name="description" type="text" value="{{ old('description') }}" placeholder="Autobús de dos pisos, 40 asientos" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @error('description')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                    Crear y editar mapa
                </button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Unidades</h3>

            @if ($busUnits->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                    Aún no hay unidades registradas.
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($busUnits as $busUnit)
                        <div class="rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <p class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $busUnit->name }}</p>
                                    <p class="mt-1 text-sm text-[#2B1113]/60">{{ $busUnit->description ?? 'Sin descripción' }}</p>
                                    <p class="mt-0.5 text-sm text-[#2B1113]/60">{{ $busUnit->seats_count }} asiento{{ $busUnit->seats_count === 1 ? '' : 's' }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $busUnit->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $busUnit->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>

                            <div class="mt-3 flex items-center gap-2">
                                <a href="{{ route('admin.unidades.edit', $busUnit) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                                    Editar mapa
                                </a>
                                <form method="POST" action="{{ route('admin.unidades.destroy', $busUnit) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700" onclick="return confirm('¿Deseas eliminar esta unidad? Los viajes que la usan quedarán sin mapa de asientos.')">
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
</x-admin-layout>
