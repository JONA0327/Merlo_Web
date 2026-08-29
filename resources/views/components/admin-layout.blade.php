@props(['active' => null, 'title' => 'Panel de administración'])

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
                <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.dashboard') : route('admin.paqueteria') }}" class="flex h-20 items-center px-6 border-b border-white/10">
                    <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-9 w-auto brightness-0 invert">
                </a>

                <nav class="flex-1 space-y-1 px-4 py-6">
                    @if (auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'dashboard' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                            Resumen
                        </a>

                        <a href="{{ route('admin.viajes') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'viajes' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v6a2 2 0 002 2h1.05a2.5 2.5 0 014.9 0H12a1 1 0 001-1v-2h2.05a1 1 0 00.923-.617l1.027-2.47A1 1 0 0016.028 6H14V5a1 1 0 00-1-1H3z"/><path d="M6 15.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM14.5 15.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                            Viajes
                        </a>

                    @endif

                    <a href="{{ route('admin.paqueteria') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'paqueteria' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A1.001 1.001 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58v1.42a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.254.716a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.512.874l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.75 1a1 1 0 01-.992 0l-1.75-1a1 1 0 01-.372-1.364z" clip-rule="evenodd"/></svg>
                        Paquetería
                    </a>

                    @if (auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.unidades') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'unidades' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v3.5A2.25 2.25 0 0115.75 10H4.25A2.25 2.25 0 012 7.75v-3.5zm14.5 8.5a.75.75 0 00-1.5 0v.5c0 .69-.56 1.25-1.25 1.25h-7.5C5.56 14.5 5 13.94 5 13.25v-.5a.75.75 0 00-1.5 0v.5A2.75 2.75 0 006.25 16h7.5a2.75 2.75 0 002.75-2.75v-.5z" clip-rule="evenodd"/></svg>
                            Distribución de Asientos
                        </a>

                        <a href="{{ route('admin.asientos.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'asientos' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                            Apartar asientos
                        </a>

                        <a href="{{ route('admin.precios.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'precios' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.75a.75.75 0 00-1.5 0v.541c-.42.087-.808.222-1.147.414-1.012.572-1.353 1.61-.786 2.629.317.567.875.92 1.446 1.148.453.18.881.31 1.237.45v1.698c-.42-.087-.808-.222-1.147-.414-.591-.333-1.105-.86-1.244-1.546a.75.75 0 00-1.48.253c.243 1.332 1.156 2.247 2.184 2.785.378.197.74.327 1.087.404v.518a.75.75 0 001.5 0v-.518c.42-.087.808-.222 1.147-.414 1.012-.572 1.353-1.61.786-2.629-.317-.567-.875-.92-1.446-1.148a10.21 10.0 00-1.237-.45v-1.698c.42.087.808.222 1.147.414.591.333 1.105.86 1.244 1.546a.75.75 0 001.48-.253c-.243-1.332-1.156-2.247-2.184-2.785A4.59 4.59 0 0010.75 6.79V6.25z" clip-rule="evenodd"/></svg>
                            Precios de boleto
                        </a>

                        <a href="{{ route('admin.checkin.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'checkin' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm6-9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1V4zm0 9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3zm6-9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1V4zm0 9a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3zM2 14a1 1 0 011-1h16a1 1 0 011 1v3a1 1 0 01-1 1H3a1 1 0 01-1-1v-3z" clip-rule="evenodd"/></svg>
                            Check-in
                        </a>

                        <a href="{{ route('admin.ventas') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'ventas' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 10.818v2.614A3.13 3.13 0 0011.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 00-1.138-.432zM8.33 8.62c.053.055.115.11.184.164.208.16.46.284.736.363V6.603a2.45 2.45 0 00-.35.13c-.14.065-.27.143-.386.233-.377.292-.514.627-.514.909 0 .184.058.39.202.591.036.05.08.1.128.152z"/><path fill-rule="evenodd" d="M11.999 3a1 1 0 10-2 0v.42a4.617 4.617 0 00-1.997.798c-.55.425-.999 1.09-.999 1.897 0 .84.46 1.487 1.058 1.913.328.235.703.415 1.101.552v2.647a2.87 2.87 0 01-.734-.363 1 1 0 10-1.229 1.578A4.891 4.891 0 009 13.417V14a1 1 0 102 0v-.428a4.62 4.62 0 001.997-.798c.55-.425.999-1.09.999-1.897 0-.84-.46-1.487-1.058-1.913A4.294 4.294 0 0011 8.417V5.905a2.5 2.5 0 01.573.257 1 1 0 10.923-1.775 4.618 4.618 0 00-1.497-.552V3z" clip-rule="evenodd"/></svg>
                            Ventas
                        </a>

                        <a href="{{ route('admin.pagos.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'pagos' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 014 2.5h12A1.5 1.5 0 0117.5 4v3a1.5 1.5 0 01-1.5 1.5h-12A1.5 1.5 0 012.5 7V4zM2 11.5A1.5 1.5 0 013.5 10h13a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 012 16v-4.5z" clip-rule="evenodd"/><path d="M5 14a1 1 0 100-2 1 1 0 000 2zM5 6a1 1 0 100-2 1 1 0 000 2z"/></svg>
                            Pagos
                        </a>

                        <div class="pt-4 mt-4 border-t border-white/10">
                            <p class="px-4 pb-2 text-xs font-bold uppercase tracking-wider text-white/40">Administración</p>
                            <a href="{{ route('admin.usuarios.create') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'usuarios' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M7 9a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z"/></svg>
                                Usuarios
                            </a>
                            <a href="{{ route('admin.configuraciones') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors {{ $active === 'configuraciones' ? 'bg-[#8C1D2B] text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 01-.517.608 7.45 7.45 0 00-.478.198.798.798 0 01-.796-.064l-.453-.324a1.875 1.875 0 00-2.416.2l-.243.243a1.875 1.875 0 00-.2 2.416l.324.453a.798.798 0 01.064.796 7.448 7.448 0 00-.198.478.798.798 0 01-.608.517l-.549.09A1.875 1.875 0 002.25 11.079v.344c0 .917.663 1.699 1.567 1.85l.549.091c.281.047.517.238.608.517.06.162.127.321.198.478a.798.798 0 01-.064.796l-.324.453a1.875 1.875 0 00.2 2.416l.243.243c.648.648 1.67.733 2.416.2l.453-.324a.798.798 0 01.796-.064c.157.071.316.137.478.198.279.091.47.327.517.608l.09.549a1.875 1.875 0 001.851 1.567h.344c.917 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 01.517-.608 7.52 7.52 0 00.478-.198.798.798 0 01.796.064l.453.324a1.875 1.875 0 002.416-.2l.243-.243c.648-.648.733-1.67.2-2.416l-.324-.453a.798.798 0 01-.064-.796c.071-.157.137-.316.198-.478.091-.279.327-.47.608-.517l.549-.09a1.875 1.875 0 001.567-1.85v-.345c0-.917-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 01-.608-.517 7.507 7.507 0 00-.198-.478.798.798 0 01.064-.796l.324-.453a1.875 1.875 0 00-.2-2.416l-.243-.243a1.875 1.875 0 00-2.416-.2l-.453.324a.798.798 0 01-.796.064 7.462 7.462 0 00-.478-.198.798.798 0 01-.517-.608l-.091-.549A1.875 1.875 0 0011.422 2.25h-.344zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" clip-rule="evenodd"/></svg>
                                Configuraciones
                            </a>
                        </div>
                    @endif
                </nav>

                <div class="p-4 border-t border-white/10">
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
                    @php
                        $mobileLinks = auth()->user()->isSuperAdmin()
                            ? [
                                ['key' => 'dashboard', 'label' => 'Resumen', 'route' => 'admin.dashboard'],
                                ['key' => 'viajes', 'label' => 'Viajes', 'route' => 'admin.viajes'],
                                ['key' => 'paqueteria', 'label' => 'Paquetería', 'route' => 'admin.paqueteria'],
                                ['key' => 'unidades', 'label' => 'Plantilla', 'route' => 'admin.unidades'],
                                ['key' => 'asientos', 'label' => 'Apartar', 'route' => 'admin.asientos.index'],
                                ['key' => 'precios', 'label' => 'Precios', 'route' => 'admin.precios.index'],
                                ['key' => 'checkin', 'label' => 'Check-in', 'route' => 'admin.checkin.index'],
                                ['key' => 'ventas', 'label' => 'Ventas', 'route' => 'admin.ventas'],
                                ['key' => 'pagos', 'label' => 'Pagos', 'route' => 'admin.pagos.index'],
                                ['key' => 'usuarios', 'label' => 'Usuarios', 'route' => 'admin.usuarios.create'],
                                ['key' => 'configuraciones', 'label' => 'Config.', 'route' => 'admin.configuraciones'],
                            ]
                            : [
                                ['key' => 'paqueteria', 'label' => 'Paquetería', 'route' => 'admin.paqueteria'],
                            ];
                    @endphp
                    @foreach ($mobileLinks as $link)
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
