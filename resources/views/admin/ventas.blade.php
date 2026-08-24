<x-admin-layout active="ventas" title="Ventas">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Ventas</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Boletos y envíos de paquetería vendidos.</p>
    </div>

    <x-empty-state
        title="Aún no hay ventas registradas"
        description="Cuando se realicen ventas de boletos o paquetería, aparecerán aquí."
    >
        <x-slot name="icon">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.75 10.818v2.614A3.13 3.13 0 0011.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 00-1.138-.432zM8.33 8.62c.053.055.115.11.184.164.208.16.46.284.736.363V6.603a2.45 2.45 0 00-.35.13c-.14.065-.27.143-.386.233-.377.292-.514.627-.514.909 0 .184.058.39.202.591.036.05.08.1.128.152z"/><path fill-rule="evenodd" d="M11.999 3a1 1 0 10-2 0v.42a4.617 4.617 0 00-1.997.798c-.55.425-.999 1.09-.999 1.897 0 .84.46 1.487 1.058 1.913.328.235.703.415 1.101.552v2.647a2.87 2.87 0 01-.734-.363 1 1 0 10-1.229 1.578A4.891 4.891 0 009 13.417V14a1 1 0 102 0v-.428a4.62 4.62 0 001.997-.798c.55-.425.999-1.09.999-1.897 0-.84-.46-1.487-1.058-1.913A4.294 4.294 0 0011 8.417V5.905a2.5 2.5 0 01.573.257 1 1 0 10.923-1.775 4.618 4.618 0 00-1.497-.552V3z" clip-rule="evenodd"/></svg>
        </x-slot>
    </x-empty-state>
</x-admin-layout>
