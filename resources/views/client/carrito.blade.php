<x-client-layout active="carrito" title="Carrito">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Carrito</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Boletos y envíos que agregaste y aún no has pagado.</p>
    </div>

    <x-empty-state
        title="Tu carrito está vacío"
        description="Los boletos o envíos de paquetería que agregues aparecerán aquí antes de pagarlos."
    >
        <x-slot name="icon">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path d="M1 1.75A.75.75 0 011.75 1h1.628a1.75 1.75 0 011.734 1.51L5.18 3h12.07a.75.75 0 01.728.92l-1.15 4.816a1.75 1.75 0 01-1.702 1.334H6.98a1.75 1.75 0 01-1.734-1.51L4.32 2.6a.25.25 0 00-.247-.216H1.75A.75.75 0 011 1.75zM6 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM14.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
        </x-slot>
    </x-empty-state>

    <div class="mt-4 text-center">
        <a href="{{ url('/#buscar') }}" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
            Buscar boletos
        </a>
    </div>
</x-client-layout>
