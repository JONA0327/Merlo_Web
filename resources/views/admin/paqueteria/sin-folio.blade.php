<x-admin-layout active="paqueteria" title="Registrar entregado sin folio · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Registrar entregado sin folio</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Para paquetes que ya se entregaron y nunca se capturaron. Solo nombre y costo; no se agrega a Clientes. Lo pagado entra al corte del día de entrega y lo que debe sale en la lista de pendientes del corte. No pide teléfono ni fotos, ni avisa por WhatsApp.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 max-w-lg rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif

    <div class="max-w-lg rounded-2xl bg-white p-6 sm:p-8 ring-1 ring-black/5 shadow-sm">
        <form method="POST" action="{{ route('admin.paqueteria.sin-folio.store') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="client_name" value="Nombre del cliente" />
                <x-text-input id="client_name" name="client_name" type="text" class="block mt-1.5 w-full" :value="old('client_name')" required autofocus />
                <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="price" value="Costo del envío ($)" />
                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="block mt-1.5 w-full" :value="old('price')" required />
                <x-input-error :messages="$errors->get('price')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="owed" value="Cuánto quedó a deber ($)" />
                <x-text-input id="owed" name="owed" type="number" step="0.01" min="0" class="block mt-1.5 w-full" :value="old('owed', 0)" />
                <p class="mt-1.5 text-xs text-[#2B1113]/50">Deja 0 si ya lo pagó completo. Lo demás se considera cobrado y entra al corte de la fecha de entrega.</p>
                <x-input-error :messages="$errors->get('owed')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="delivered_on" value="Fecha de entrega (opcional)" />
                <x-text-input id="delivered_on" name="delivered_on" type="date" max="{{ now()->toDateString() }}" class="block mt-1.5 w-full" :value="old('delivered_on')" />
                <x-input-error :messages="$errors->get('delivered_on')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.paqueteria') }}" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-[#2B1113]/60 hover:bg-black/5 transition-colors">Cancelar</a>
                <x-primary-button type="submit">Registrar</x-primary-button>
            </div>
        </form>
    </div>
</x-admin-layout>
