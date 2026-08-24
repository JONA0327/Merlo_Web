@props(['active' => null, 'title' => 'Mi cuenta'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} · Merlo Transportes</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:500,600,700,800|instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[#2B1113] antialiased">
        <div class="flex min-h-screen bg-[#FFFBF6]">
            {{-- ===================== SIDEBAR ===================== --}}
            <aside class="hidden lg:flex w-72 shrink-0 flex-col bg-[#2B1113] text-white">
                <a href="{{ route('dashboard') }}" class="flex h-20 items-center px-6 border-b border-white/10">
                    <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-9 w-auto brightness-0 invert">
                </a>

                <nav class="flex-1 space-y-1 px-4 py-6">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'dashboard' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                        Resumen
                    </a>

                    <a href="{{ route('cliente.boletos') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'boletos' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 3.75a3 3 0 00-3 3v.75a.75.75 0 00.75.75 1.5 1.5 0 010 3 .75.75 0 00-.75.75v.75a3 3 0 003 3h11a3 3 0 003-3v-.75a.75.75 0 00-.75-.75 1.5 1.5 0 010-3 .75.75 0 00.75-.75v-.75a3 3 0 00-3-3h-11zM8 6a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 018 6zm.75 3.25a.75.75 0 00-1.5 0v.5a.75.75 0 001.5 0v-.5zM8 12a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 018 12z" clip-rule="evenodd"/></svg>
                        Mis boletos
                    </a>

                    <a href="{{ route('cliente.paquetes') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'paquetes' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
                        Mis paquetes
                    </a>

                    <a href="{{ route('cliente.carrito') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'carrito' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M1 1.75A.75.75 0 011.75 1h1.628a1.75 1.75 0 011.734 1.51L5.18 3h12.07a.75.75 0 01.728.92l-1.15 4.816a1.75 1.75 0 01-1.702 1.334H6.98a1.75 1.75 0 01-1.734-1.51L4.32 2.6a.25.25 0 00-.247-.216H1.75A.75.75 0 011 1.75zM6 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM14.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                        Carrito
                    </a>

                    <a href="{{ route('cliente.compras') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'compras' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.914a2 2 0 00-.586-1.414l-3.914-3.914A2 2 0 0011.086 2H4zm2 10a1 1 0 100 2h4a1 1 0 100-2H6zm0-4a1 1 0 100 2h4a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                        Mis compras
                    </a>
                </nav>

                <div class="p-4 border-t border-white/10 space-y-1">
                    <a href="{{ url('/#buscar') }}" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold bg-[#8C1D2B] hover:bg-[#6F1622] transition-colors">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                        Comprar boleto
                    </a>
                    <a href="{{ url('/') }}" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white/60 hover:bg-white/5 hover:text-white transition-colors">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z" clip-rule="evenodd"/></svg>
                        Volver al sitio
                    </a>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- ===================== TOPBAR ===================== --}}
                <header class="flex h-20 items-center justify-between border-b border-black/5 bg-white px-6 lg:px-8">
                    <div class="flex items-center gap-3 lg:hidden">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-8 w-auto">
                    </div>
                    <h1 class="hidden lg:block font-[Poppins] text-xl font-bold text-[#2B1113]">{{ $title }}</h1>

                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-3 rounded-full py-1.5 pl-1.5 pr-3 hover:bg-black/5 transition-colors">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#8C1D2B] text-xs font-bold text-white">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden sm:block text-sm font-semibold text-[#2B1113]">{{ auth()->user()->name }}</span>
                                <svg class="h-4 w-4 text-[#2B1113]/40" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-2 border-b border-black/5">
                                <p class="text-sm font-semibold text-[#2B1113]">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-[#2B1113]/50">{{ auth()->user()->email }}</p>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">Mi perfil</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    Cerrar sesión
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                {{-- ===================== NAV MÓVIL ===================== --}}
                <nav class="lg:hidden flex items-center gap-2 overflow-x-auto border-b border-black/5 bg-white px-4 py-3">
                    @foreach ([
                        ['key' => 'dashboard', 'label' => 'Resumen', 'route' => 'dashboard'],
                        ['key' => 'boletos', 'label' => 'Mis boletos', 'route' => 'cliente.boletos'],
                        ['key' => 'paquetes', 'label' => 'Mis paquetes', 'route' => 'cliente.paquetes'],
                        ['key' => 'carrito', 'label' => 'Carrito', 'route' => 'cliente.carrito'],
                        ['key' => 'compras', 'label' => 'Mis compras', 'route' => 'cliente.compras'],
                    ] as $link)
                        <a href="{{ route($link['route']) }}" class="shrink-0 rounded-full px-4 py-2 text-xs font-bold transition-colors {{ $active === $link['key'] ? 'bg-[#8C1D2B] text-white' : 'bg-[#FFFBF6] text-[#2B1113]/60' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>

                {{-- ===================== CONTENIDO ===================== --}}
                <main class="flex-1 p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
