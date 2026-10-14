<x-admin-layout active="paqueteria" title="Imprimir guía de folios · Paquetería">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Imprimir guía de folios</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Imprime una hoja de folios en blanco en etiquetas de papel para pegar en los paquetes antes de registrarlos. Los folios nunca vencen — si sobran, sirven para la próxima vez.</p>
        </div>
        <a href="{{ route('admin.paqueteria.qr.batches') }}" class="shrink-0 rounded-xl bg-black/5 px-4 py-2.5 text-sm font-bold text-[#2B1113] hover:bg-black/10 transition-colors">
            Lotes generados
        </a>
    </div>

    <div class="mb-6 max-w-lg rounded-2xl bg-[#FFFBF6] px-4 py-3 text-xs text-[#2B1113]/50">
        Estos folios son solo etiquetas en blanco para pegar en los paquetes — el registro real (nombre, teléfono, total) se hace escaneando el QR universal en "Registrar nuevo", no aquí.
    </div>

    <div class="max-w-lg rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.paqueteria.folios.store') }}" class="space-y-6">
            @csrf

            <div>
                <x-input-label for="qty" value="Cantidad de folios" />
                <x-text-input id="qty" name="qty" type="number" min="1" max="200" class="block mt-1.5 w-full" value="{{ old('qty', 24) }}" />
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Hasta 200 por lote.</p>
                <x-input-error :messages="$errors->get('qty')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.paqueteria') }}" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">
                    Cancelar
                </a>
                <x-primary-button type="submit">
                    Generar
                </x-primary-button>
            </div>
        </form>
    </div>
</x-admin-layout>
