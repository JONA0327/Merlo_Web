<x-client-layout active="dashboard" title="Resumen">
    <div class="mb-8">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Hola, {{ explode(' ', auth()->user()->name)[0] }} 👋</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Este es el resumen de tu cuenta en Merlo Transportes.</p>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        @foreach ([
            ['label' => 'En tu carrito', 'value' => '0', 'icon' => 'cart'],
            ['label' => 'Boletos activos', 'value' => '0', 'icon' => 'ticket'],
            ['label' => 'Paquetes en camino', 'value' => '0', 'icon' => 'box'],
        ] as $stat)
            <div class="rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8C1D2B]/10 text-[#8C1D2B]">
                    @switch($stat['icon'])
                        @case('cart')
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M1 1.75A.75.75 0 011.75 1h1.628a1.75 1.75 0 011.734 1.51L5.18 3h12.07a.75.75 0 01.728.92l-1.15 4.816a1.75 1.75 0 01-1.702 1.334H6.98a1.75 1.75 0 01-1.734-1.51L4.32 2.6a.25.25 0 00-.247-.216H1.75A.75.75 0 011 1.75zM6 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM14.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                            @break
                        @case('ticket')
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 3.75a3 3 0 00-3 3v.75a.75.75 0 00.75.75 1.5 1.5 0 010 3 .75.75 0 00-.75.75v.75a3 3 0 003 3h11a3 3 0 003-3v-.75a.75.75 0 00-.75-.75 1.5 1.5 0 010-3 .75.75 0 00.75-.75v-.75a3 3 0 00-3-3h-11zM8 6a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 018 6zm.75 3.25a.75.75 0 00-1.5 0v.5a.75.75 0 001.5 0v-.5zM8 12a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 018 12z" clip-rule="evenodd"/></svg>
                            @break
                        @case('box')
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
                            @break
                    @endswitch
                </span>
                <p class="mt-4 font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $stat['value'] }}</p>
                <p class="text-sm text-[#2B1113]/60">{{ $stat['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
        @foreach ([
            ['label' => 'Mis boletos', 'route' => 'cliente.boletos', 'description' => 'Consulta tus boletos comprados y próximos viajes.'],
            ['label' => 'Mis paquetes', 'route' => 'cliente.paquetes', 'description' => 'Da seguimiento a tus envíos de paquetería.'],
            ['label' => 'Carrito', 'route' => 'cliente.carrito', 'description' => 'Boletos y envíos que aún no has pagado.'],
            ['label' => 'Mis compras', 'route' => 'cliente.compras', 'description' => 'Historial completo de tus compras.'],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="group rounded-2xl bg-white p-5 ring-1 ring-black/5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                <p class="font-[Poppins] font-bold text-[#2B1113]">{{ $card['label'] }}</p>
                <p class="mt-1 text-sm text-[#2B1113]/60">{{ $card['description'] }}</p>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[#8C1D2B]">
                    Ver
                    <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
                </span>
            </a>
        @endforeach
    </div>
</x-client-layout>
