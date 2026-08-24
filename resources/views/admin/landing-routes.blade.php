<x-admin-layout active="landing-routes" title="Rutas de la landing">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Rutas destacadas</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Administra las rutas que aparecen en la landing principal del sitio.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_1.8fr]">
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Agregar ruta</h3>

            <form method="POST" action="{{ route('admin.landing-routes.store') }}" class="mt-6 space-y-4">
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
                        <label for="duration" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Duración</label>
                        <input id="duration" name="duration" type="text" value="{{ old('duration') }}" placeholder="6h 30m" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                        @error('duration')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="price" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Precio</label>
                        <input id="price" name="price" type="text" value="{{ old('price') }}" placeholder="$650" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                        @error('price')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Orden</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    </div>

                    <div class="flex items-end">
                        <label class="flex w-full items-center justify-between rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-semibold text-[#2B1113]">
                            <span>Visible</span>
                            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" checked>
                        </label>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                    Guardar ruta
                </button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Rutas actuales</h3>

            @if ($routes->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                    Todavía no hay rutas configuradas.
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($routes as $route)
                        <div class="rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $route->from }} → {{ $route->to }}</p>
                                    <p class="mt-1 text-sm text-[#2B1113]/60">{{ $route->duration }} · {{ $route->price }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $route->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $route->is_active ? 'Visible' : 'Oculta' }}
                                </span>
                            </div>

                            <form method="POST" action="{{ route('admin.landing-routes.destroy', $route) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700" onclick="return confirm('¿Deseas eliminar esta ruta?')">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-admin-layout>
