<x-admin-layout active="destinations" title="Editar destino">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.destinations.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#8C1D2B] hover:text-[#6F1622]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17.5 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h11.19A.75.75 0 0117.5 10z" fill-rule="evenodd"/></svg>
                Volver a Destinos
            </a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Editar destino: {{ $destination->name }}</h2>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.destinations.update', $destination) }}" class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            <div class="sm:col-span-7">
                <label for="name" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Nombre</label>
                <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', $destination->name) }}" placeholder="Ciudad de México" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                @error('name') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-3">
                <label for="code" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Código <span class="font-normal text-[#2B1113]/50">(opcional)</span></label>
                <input id="code" name="code" type="text" maxlength="16" value="{{ old('code', $destination->code) }}" placeholder="CDMX" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-mono text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                @error('code') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 flex items-end">
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-3 text-sm text-[#2B1113]">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $destination->is_active)) class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]">
                    <span class="font-semibold">Activo</span>
                </label>
            </div>
        </div>
        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('admin.destinations.index') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-[#2B1113]/60 ring-1 ring-black/10 hover:bg-black/5">Cancelar</a>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">Guardar cambios</button>
        </div>
    </form>
</x-admin-layout>