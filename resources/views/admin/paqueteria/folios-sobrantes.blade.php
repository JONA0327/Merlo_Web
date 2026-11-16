<x-admin-layout active="paqueteria" title="Folios sin usar · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria.folios.create') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Sobraron folios de la vez anterior</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">
            Sobraron {{ $leftoverCount }} folio{{ $leftoverCount === 1 ? '' : 's' }} sin usar ({{ $leftoverRange }}).
            ¿Quieres agregarlos a esta impresión de {{ $qty }} folio{{ $qty === 1 ? '' : 's' }}?
        </p>
    </div>

    <div class="max-w-lg rounded-2xl bg-[#FFFBF6] px-4 py-3 text-xs text-[#2B1113]/50 mb-6">
        Si ya imprimiste esos folios antes y las hojas siguen por ahí, elige "No" para que esta hoja traiga puros folios nuevos y no se repita ningún número. Si no los habías impreso o ya no tienes esas hojas, elige "Sí" para no dejarlos perdidos.
    </div>

    <div class="max-w-lg flex flex-col sm:flex-row gap-3">
        <form method="POST" action="{{ route('admin.paqueteria.folios.store') }}" class="flex-1">
            @csrf
            <input type="hidden" name="qty" value="{{ $qty }}">
            <input type="hidden" name="confirmed" value="1">
            <input type="hidden" name="include_leftovers" value="0">
            <button type="submit" class="w-full rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                No, solo folios nuevos
            </button>
        </form>
        <form method="POST" action="{{ route('admin.paqueteria.folios.store') }}" class="flex-1">
            @csrf
            <input type="hidden" name="qty" value="{{ $qty }}">
            <input type="hidden" name="confirmed" value="1">
            <input type="hidden" name="include_leftovers" value="1">
            <x-primary-button type="submit" class="w-full justify-center">
                Sí, agregarlos
            </x-primary-button>
        </form>
    </div>
</x-admin-layout>
