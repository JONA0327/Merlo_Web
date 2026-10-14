<x-admin-layout active="dashboard" title="Resumen">
    <div class="mb-8">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Hola, {{ explode(' ', auth()->user()->name)[0] }} 👋</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Este es el panel de administración de Merlo Transportes.</p>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Viajes activos', 'value' => $activeTripsCount, 'icon' => 'bus', 'route' => 'admin.viajes'],
            ['label' => 'Envíos de paquetería', 'value' => $activePackagesCount, 'icon' => 'box', 'route' => 'admin.paqueteria'],
        ] as $stat)
            <a href="{{ route($stat['route']) }}" class="rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8C1D2B]/10 text-[#8C1D2B]">
                    @switch($stat['icon'])
                        @case('bus')
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v6a2 2 0 002 2h1.05a2.5 2.5 0 014.9 0H12a1 1 0 001-1v-2h2.05a1 1 0 00.923-.617l1.027-2.47A1 1 0 0016.028 6H14V5a1 1 0 00-1-1H3z"/><path d="M6 15.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM14.5 15.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                            @break
                        @case('box')
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
                            @break
                    @endswitch
                </span>
                <p class="mt-4 font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $stat['value'] }}</p>
                <p class="text-sm text-[#2B1113]/60">{{ $stat['label'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-3">
        @foreach ([
            ['label' => 'Viajes', 'route' => 'admin.viajes', 'description' => 'Rutas, horarios y unidades.'],
            ['label' => 'Paquetería', 'route' => 'admin.paqueteria', 'description' => 'Envíos y seguimiento.'],
            ['label' => 'Distribución de Asientos', 'route' => 'admin.unidades', 'description' => 'Mapas de asientos por unidad.'],
            ['label' => 'Usuarios', 'route' => 'admin.usuarios.index', 'description' => 'Clientes registrados y cuentas internas.'],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="group rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $card['label'] }}</p>
                <p class="mt-1 text-sm text-[#2B1113]/60">{{ $card['description'] }}</p>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[#8C1D2B]">
                    Ir a la sección
                    <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
                </span>
            </a>
        @endforeach
    </div>
</x-admin-layout>
