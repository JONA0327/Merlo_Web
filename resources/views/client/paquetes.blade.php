<x-client-layout active="paquetes" title="Mis paquetes">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Mis paquetes</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Da seguimiento a tus envíos de paquetería.</p>
    </div>

    <x-empty-state
        title="Aún no tienes envíos"
        description="Cuando envíes un paquete con nosotros, podrás dar seguimiento a su estatus desde aquí."
    >
        <x-slot name="icon">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
        </x-slot>
    </x-empty-state>

    <div class="mt-4 text-center">
        <a href="{{ url('/#paqueteria') }}" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
            Cotizar un envío
        </a>
    </div>
</x-client-layout>
