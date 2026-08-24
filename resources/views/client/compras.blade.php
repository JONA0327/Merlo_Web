<x-client-layout active="compras" title="Mis compras">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Mis compras</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Historial completo de tus compras de boletos y paquetería.</p>
    </div>

    <x-empty-state
        title="Aún no tienes compras"
        description="Cuando completes una compra, aparecerá aquí con su recibo y detalles."
    >
        <x-slot name="icon">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.914a2 2 0 00-.586-1.414l-3.914-3.914A2 2 0 0011.086 2H4zm2 10a1 1 0 100 2h4a1 1 0 100-2H6zm0-4a1 1 0 100 2h4a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
        </x-slot>
    </x-empty-state>
</x-client-layout>
