<x-admin-layout active="paqueteria" title="Paquetería">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Paquetería</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Registra y da seguimiento a los envíos de paquetería.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.paqueteria.corte') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                Corte del día
            </a>
            <a href="{{ route('admin.paqueteria.sin-folio.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                Registrar entregado sin folio
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div class="rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm">
            <p class="font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $recolectadosHoy }}</p>
            <p class="text-sm text-[#2B1113]/60">Recolectados hoy</p>
        </div>
        <div class="rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm">
            <p class="font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $entregadosHoy }}</p>
            <p class="text-sm text-[#2B1113]/60">Entregados hoy</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            [
                'label' => 'Registrar nuevo',
                'route' => 'admin.paqueteria.registrar.create',
                'description' => 'Captura los datos del cliente y asigna folio(s) a su envío.',
                'icon' => '<path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>',
                'highlight' => true,
            ],
            [
                'label' => 'Generar folios',
                'route' => 'admin.paqueteria.folios.create',
                'description' => 'Imprime etiquetas de folio en blanco para pegar en los paquetes antes de registrarlos.',
                'icon' => '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>',
            ],
            [
                'label' => 'Clientes',
                'route' => 'admin.paqueteria.clientes',
                'description' => 'Busca un cliente, revisa sus envíos y cobra saldos pendientes.',
                'icon' => '<path d="M10 9a3 3 0 100-6 3 3 0 000 6zM4 18a6 6 0 1112 0H4z"/>',
            ],
            [
                'label' => 'Paquetes',
                'route' => 'admin.paqueteria.paquetes.index',
                'description' => 'Los paquetes del día: cuáles van recolectados y cuáles ya se entregaron.',
                'icon' => '<path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/>',
            ],
            [
                'label' => 'Código QR',
                'route' => 'admin.paqueteria.qr-universal',
                'description' => 'El QR fijo para registrar sin entrar al panel — descárgalo o imprímelo.',
                'icon' => '<path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 1v2h2V5H5zM3 12a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1v-4zm2 1v2h2v-2H5zM11 4a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4zm2 1v2h2V5h-2zM11 12a1 1 0 011-1h1a1 1 0 110 2h-1v1h1a1 1 0 110 2h-2a1 1 0 01-1-1v-3zM16 12a1 1 0 112 0v1a1 1 0 11-2 0v-1zM15 16a1 1 0 100 2h2a1 1 0 100-2h-2z" clip-rule="evenodd"/>',
            ],
            [
                'label' => 'Historial',
                'route' => 'admin.paqueteria.historial',
                'description' => 'Registro permanente de entregas, con el cliente de cada envío — nunca se borra.',
                'icon' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>',
            ],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="group rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['highlight'] ?? false ? 'bg-[#8C1D2B] text-white' : 'bg-[#8C1D2B]/10 text-[#8C1D2B]' }}">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">{!! $card['icon'] !!}</svg>
                </span>
                <p class="mt-4 font-[Poppins] font-bold text-[#2B1113]">{{ $card['label'] }}</p>
                <p class="mt-1 text-sm text-[#2B1113]/60">{{ $card['description'] }}</p>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[#8C1D2B]">
                    Ir a la sección
                    <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
                </span>
            </a>
        @endforeach
    </div>
</x-admin-layout>
