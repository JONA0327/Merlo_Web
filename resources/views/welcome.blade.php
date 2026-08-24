<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Merlo Transportes') }} · Boletos de autobús</title>
        <meta name="description" content="Compra tus boletos de autobús con Merlo Transportes: rutas seguras, horarios flexibles y el mejor servicio en carretera.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:500,600,700,800|instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">

        {{-- ===================== HEADER ===================== --}}
        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-black/5">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <a href="/" class="flex items-center gap-3 shrink-0">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-11 w-auto">
                    </a>

                    <nav class="hidden lg:flex items-center gap-9 text-sm font-semibold text-[#2B1113]/70">
                        <a href="#inicio" class="hover:text-[#8C1D2B] transition-colors">Inicio</a>
                        <a href="#rutas" class="hover:text-[#8C1D2B] transition-colors">Rutas</a>
                        <a href="#beneficios" class="hover:text-[#8C1D2B] transition-colors">Beneficios</a>
                        <a href="#paqueteria" class="hover:text-[#8C1D2B] transition-colors">Paquetería</a>
                        <a href="#nosotros" class="hover:text-[#8C1D2B] transition-colors">Nosotros</a>
                        <a href="#cotizar" class="hover:text-[#8C1D2B] transition-colors">Cotizar</a>
                        <a href="#contacto" class="hover:text-[#8C1D2B] transition-colors">Contacto</a>
                    </nav>

                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center px-4 py-2 text-sm font-semibold text-[#2B1113] hover:text-[#8C1D2B] transition-colors">
                                Mi cuenta
                            </a>
                            <a href="#buscar" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                                Comprar boleto
                            </a>
                        @else
                            <button type="button" class="js-modal-trigger hidden sm:inline-flex items-center px-4 py-2 text-sm font-semibold text-[#2B1113] hover:text-[#8C1D2B] transition-colors" data-auth-modal="login">
                                Iniciar sesión
                            </button>
                            <button type="button" class="js-modal-trigger inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors" data-auth-modal="login">
                                Comprar boleto
                            </button>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        {{-- ===================== MENSAJE DE SESIÓN ===================== --}}
        @if (session('status') === 'registered')
            <div class="bg-[#FFF6DD] border-b border-[#F5B301]/40">
                <div class="mx-auto max-w-7xl px-6 lg:px-8 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-[#6B4E00] text-center sm:text-left">
                        <svg class="h-5 w-5 shrink-0 text-[#B8860B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        ¡Bienvenido{{ auth()->user()?->name ? ', '.explode(' ', auth()->user()->name)[0] : '' }}! Tu cuenta se creó correctamente. Te enviamos un código de verificación a tu correo.
                    </p>
                    <a href="{{ route('verification.notice') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6F1622] transition-colors">
                        Verificar mi correo
                    </a>
                </div>
            </div>
        @elseif (session('status') === 'verified')
            <div class="bg-green-50 border-b border-green-200">
                <div class="mx-auto max-w-7xl px-6 lg:px-8 py-3">
                    <p class="flex items-center justify-center gap-2 text-sm font-semibold text-green-700">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        ¡Tu correo fue verificado correctamente! Ya puedes comprar tus boletos.
                    </p>
                </div>
            </div>
        @elseif (session('status') === 'password-reset')
            <div class="bg-green-50 border-b border-green-200">
                <div class="mx-auto max-w-7xl px-6 lg:px-8 py-3">
                    <p class="flex items-center justify-center gap-2 text-sm font-semibold text-green-700">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        ¡Tu contraseña se actualizó correctamente! Ya iniciaste sesión.
                    </p>
                </div>
            </div>
        @endif

        {{-- ===================== HERO ===================== --}}
        <section id="inicio" class="js-carousel group relative isolate overflow-hidden bg-[#3B0A10]">
            {{-- background photo carousel --}}
            @foreach ([
                ['src' => 'images/bus-flotilla.jpg', 'alt' => 'Flotilla de autobuses de Merlo Transportes'],
                ['src' => 'images/bus-terminal.jpg', 'alt' => 'Autobús de Merlo Transportes en terminal'],
                ['src' => 'images/bus-carretera-noche.jpg', 'alt' => 'Autobús de Merlo Transportes viajando de noche'],
            ] as $index => $slide)
                <img
                    src="{{ asset($slide['src']) }}"
                    alt="{{ $slide['alt'] }}"
                    class="js-carousel-slide absolute inset-0 -z-20 h-full w-full object-cover transition-opacity duration-[1500ms] ease-in-out {{ $index === 0 ? 'opacity-100' : 'opacity-0' }}"
                    @if ($index > 0) aria-hidden="true" @endif
                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                >
            @endforeach

            {{-- brand overlay for legibility --}}
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#3B0A10] via-[#3B0A10]/90 to-[#3B0A10]/40"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-[#2B0509]/90 via-transparent to-[#2B0509]/40"></div>
            <div class="absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-[#F5B301]/10 blur-3xl"></div>

            <svg class="absolute inset-x-0 bottom-0 w-full text-[#FFFBF6]" viewBox="0 0 1440 60" fill="currentColor" preserveAspectRatio="none" style="height:60px">
                <path d="M0 60L60 51.7C120 43 240 27 360 22.5C480 18 600 27 720 33.8C840 41 960 47 1080 43.3C1200 40 1320 27 1380 20.8L1440 15V60H1380C1320 60 1200 60 1080 60C960 60 840 60 720 60C600 60 480 60 360 60C240 60 120 60 60 60H0Z"/>
            </svg>

            <div class="relative mx-auto max-w-7xl px-6 pt-20 pb-44 lg:px-8 lg:pt-28 lg:pb-56">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-[#F5C948] ring-1 ring-white/20">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 01.894.553l1.382 2.764 3.05.443a1 1 0 01.554 1.706l-2.207 2.152.521 3.037a1 1 0 01-1.451 1.054L10 12.347l-2.723 1.432a1 1 0 01-1.451-1.054l.52-3.037L4.14 7.466a1 1 0 01.554-1.706l3.05-.443L9.106 2.553A1 1 0 0110 2z"/></svg>
                        Más de 20 años en carretera
                    </span>
                    <h1 class="mt-6 font-[Poppins] text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.05] text-white [text-shadow:0_2px_20px_rgba(0,0,0,0.35)]">
                        Viaja seguro,<br>
                        viaja con <span class="text-[#F5C948]">Merlo Transportes</span>
                    </h1>
                    <p class="mt-6 max-w-lg text-lg text-white/85">
                        Compra tus boletos de autobús en minutos. Rutas puntuales, unidades cómodas y la mejor atención en cada tramo de tu viaje.
                    </p>

                    <dl class="mt-10 grid grid-cols-3 gap-6 max-w-md">
                        <div>
                            <dt class="sr-only">Rutas</dt>
                            <dd class="font-[Poppins] text-3xl font-extrabold text-white">40+</dd>
                            <dd class="text-sm text-white/60">Rutas activas</dd>
                        </div>
                        <div>
                            <dt class="sr-only">Ciudades</dt>
                            <dd class="font-[Poppins] text-3xl font-extrabold text-white">25</dd>
                            <dd class="text-sm text-white/60">Ciudades</dd>
                        </div>
                        <div>
                            <dt class="sr-only">Pasajeros</dt>
                            <dd class="font-[Poppins] text-3xl font-extrabold text-white">1M+</dd>
                            <dd class="text-sm text-white/60">Pasajeros felices</dd>
                        </div>
                    </dl>

                    {{-- carousel controls --}}
                    <div class="mt-10 flex items-center gap-4">
                        <button type="button" class="js-carousel-prev flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20" aria-label="Foto anterior">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 010 1.06L9.06 10l3.73 3.71a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
                        </button>
                        <div class="flex items-center gap-2">
                            @for ($i = 0; $i < 3; $i++)
                                <button
                                    type="button"
                                    class="js-carousel-dot h-2 rounded-full transition-all {{ $i === 0 ? 'w-6 bg-white' : 'w-2 bg-white/40 hover:bg-white/60' }}"
                                    data-slide="{{ $i }}"
                                    aria-label="Ir a la foto {{ $i + 1 }}"
                                ></button>
                            @endfor
                        </div>
                        <button type="button" class="js-carousel-next flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20" aria-label="Foto siguiente">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 010-1.06L10.94 10 7.21 6.29a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== BUSCADOR ===================== --}}
        <section id="buscar" class="relative -mt-28 lg:-mt-32 px-6 lg:px-8">
            <div class="mx-auto max-w-5xl rounded-3xl bg-white shadow-2xl shadow-black/10 ring-1 ring-black/5 p-6 sm:p-8">
                <h2 class="font-[Poppins] text-lg font-bold text-[#2B1113] mb-5">Encuentra tu boleto</h2>
                <form method="GET" action="{{ route('travel.search') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto] gap-4">
                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-[#8C1D2B]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.69 18.933a.75.75 0 00.62 0c.058-.026 3.32-1.5 6.09-4.005A9.75 9.75 0 0010 1.5a9.75 9.75 0 00-6.4 13.428c2.77 2.505 6.032 3.979 6.09 4.005zM10 10a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" clip-rule="evenodd"/></svg>
                            Origen
                        </span>
                        <input type="text" name="from" value="{{ request('from') }}" placeholder="Ciudad de origen" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-medium text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none transition">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-[#8C1D2B]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.69 18.933a.75.75 0 00.62 0c.058-.026 3.32-1.5 6.09-4.005A9.75 9.75 0 0010 1.5a9.75 9.75 0 00-6.4 13.428c2.77 2.505 6.032 3.979 6.09 4.005zM10 10a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" clip-rule="evenodd"/></svg>
                            Destino
                        </span>
                        <input type="text" name="to" value="{{ request('to') }}" placeholder="Ciudad de destino" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-medium text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none transition">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-[#8C1D2B]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zM3.5 8.5v6.75c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25V8.5h-13z" clip-rule="evenodd"/></svg>
                            Fecha de viaje
                        </span>
                        <input type="date" name="date" value="{{ request('date') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-medium text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none transition">
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-[#8C1D2B]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zM3.5 8.5v6.75c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25V8.5h-13z" clip-rule="evenodd"/></svg>
                            Fecha de regreso
                        </span>
                        <input type="date" name="return_date" value="{{ request('return_date') }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-medium text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none transition">
                    </label>

                    <div class="flex items-end">
                        <button type="submit" class="w-full lg:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-[#F5B301] px-7 py-3 text-sm font-bold text-[#2B1113] shadow-lg shadow-[#F5B301]/30 hover:bg-[#E0A400] transition-colors">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                            Buscar
                        </button>
                    </div>
                </form>
            </div>
        </section>

        {{-- ===================== RUTAS POPULARES ===================== --}}
        <section id="rutas" class="mx-auto max-w-7xl px-6 lg:px-8 pt-24 pb-20">
            <div class="text-center max-w-2xl mx-auto">
                <span class="text-xs font-bold uppercase tracking-widest text-[#8C1D2B]">Destinos frecuentes</span>
                <h2 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-[#2B1113]">Rutas populares</h2>
                <p class="mt-3 text-[#2B1113]/60">Estas son algunas de las rutas más solicitadas por nuestros pasajeros cada semana.</p>
            </div>

            <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $landingRoutes = \App\Models\LandingRoute::query()
                        ->where('is_active', true)
                        ->where('featured', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->limit(4)
                        ->get();
                @endphp

                @forelse ($landingRoutes as $route)
                    <div class="group rounded-2xl bg-white p-6 ring-1 ring-black/5 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8C1D2B]/10 text-[#8C1D2B]">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 00-2 2v6a2 2 0 002 2h1.05a2.5 2.5 0 014.9 0H12a1 1 0 001-1v-2h2.05a1 1 0 00.923-.617l1.027-2.47A1 1 0 0016.028 6H14V5a1 1 0 00-1-1H3z"/><path d="M6 15.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Directo</span>
                        </div>
                        <p class="mt-5 font-[Poppins] text-base font-bold text-[#2B1113]">{{ $route->from }}</p>
                        <div class="my-2 flex items-center gap-2 text-[#2B1113]/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#F5B301]"></span>
                            <span class="h-px flex-1 border-t border-dashed border-current"></span>
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 10a.75.75 0 01.75-.75h11.19l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.25A.75.75 0 012.5 10z" clip-rule="evenodd"/></svg>
                            <span class="h-1.5 w-1.5 rounded-full bg-[#8C1D2B]"></span>
                        </div>
                        <p class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $route->to }}</p>

                        <div class="mt-5 flex items-center justify-between border-t border-black/5 pt-4">
                            <div>
                                <p class="text-xs text-[#2B1113]/50">Duración</p>
                                <p class="text-sm font-semibold">{{ $route->duration }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-[#2B1113]/50">Desde</p>
                                <p class="font-[Poppins] text-lg font-extrabold text-[#8C1D2B]">{{ $route->formatted_price }}</p>
                            </div>
                        </div>

                        <div class="mt-3 space-y-0.5">
                            @if ($route->day)
                                <p class="text-xs font-semibold text-[#2B1113]/50">Salida: {{ $route->day->format('d/m/Y') }}@if ($route->departure_time_formatted) &middot; {{ $route->departure_time_formatted }} @endif</p>
                            @endif
                            @if ($route->return_date)
                                <p class="text-xs font-semibold text-[#2B1113]/50">Regreso: {{ $route->return_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-[#2B1113]/60 font-medium">Viajes destacados próximamente</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ===================== BENEFICIOS ===================== --}}
        <section id="beneficios" class="bg-white border-y border-black/5">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-24">
                <div class="text-center max-w-2xl mx-auto">
                    <span class="text-xs font-bold uppercase tracking-widest text-[#8C1D2B]">¿Por qué Merlo?</span>
                    <h2 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-[#2B1113]">Viajar nunca fue tan fácil</h2>
                </div>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    @foreach ([
                        ['icon' => 'shield', 'title' => 'Viaje seguro', 'text' => 'Unidades revisadas y conductores certificados en cada salida.'],
                        ['icon' => 'clock', 'title' => 'Puntualidad', 'text' => 'Salidas y llegadas a tiempo, todos los días del año.'],
                        ['icon' => 'seat', 'title' => 'Comodidad', 'text' => 'Asientos reclinables, aire acondicionado y espacio amplio.'],
                        ['icon' => 'card', 'title' => 'Compra fácil', 'text' => 'Paga en línea y recibe tu boleto al instante, sin filas.'],
                    ] as $benefit)
                        <div class="text-center sm:text-left">
                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#8C1D2B] to-[#6F1622] text-[#F5C948] shadow-lg shadow-[#8C1D2B]/20">
                                @switch($benefit['icon'])
                                    @case('shield')
                                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.664 1.319a.75.75 0 01.672 0 41.059 41.059 0 008.198 3.222.75.75 0 01.556.712 26.65 26.65 0 01-2.005 11.245 6.716 6.716 0 01-4.14 3.997A41.058 41.058 0 0110 21.13a41.058 41.058 0 01-2.945-1.635 6.716 6.716 0 01-4.14-3.997A26.65 26.65 0 011 4.253a.75.75 0 01.556-.712 41.059 41.059 0 008.108-3.222z" clip-rule="evenodd"/></svg>
                                        @break
                                    @case('clock')
                                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                                        @break
                                    @case('seat')
                                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a1 1 0 00-1 1v6a3 3 0 003 3h.5v3a1 1 0 102 0v-3h3v3a1 1 0 102 0v-3.09A3 3 0 0016 10V6a1 1 0 10-2 0v4a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 00-1-1H4z"/></svg>
                                        @break
                                    @case('card')
                                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1v6A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5v-6zM3 13.25a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75z"/></svg>
                                        @break
                                @endswitch
                            </span>
                            <h3 class="mt-5 font-[Poppins] text-lg font-bold text-[#2B1113]">{{ $benefit['title'] }}</h3>
                            <p class="mt-2 text-sm text-[#2B1113]/60 leading-relaxed">{{ $benefit['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ===================== PAQUETERÍA ===================== --}}
        <section id="paqueteria" class="mx-auto max-w-7xl px-6 lg:px-8 py-24">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">
                <div class="order-2 lg:order-1">
                    <span class="text-xs font-bold uppercase tracking-widest text-[#8C1D2B]">También ofrecemos</span>
                    <h2 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-[#2B1113]">
                        Servicio de paquetería
                    </h2>
                    <p class="mt-4 text-[#2B1113]/60 max-w-lg">
                        Envía documentos y paquetes entre ciudades con la misma seguridad y puntualidad de nuestras rutas de pasajeros. Rápido, confiable y a un gran precio.
                    </p>

                    <ul class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg">
                        @foreach ([
                            'Entrega el mismo día entre ciudades conectadas',
                            'Rastreo de tu envío en tiempo real',
                            'Empaque y manejo seguro garantizado',
                            'Tarifas accesibles por kilo o volumen',
                        ] as $item)
                            <li class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#F5B301]/20 text-[#8C1D2B]">
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                </span>
                                <span class="text-sm text-[#2B1113]/80">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-9 flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('paqueteria.rastreo') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-[#8C1D2B] px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                            Rastrear paquete
                        </a>
                    </div>
                </div>

                <div class="order-1 lg:order-2 flex justify-center">
                    <div class="relative w-full max-w-md aspect-square rounded-[2.5rem] bg-gradient-to-br from-[#8C1D2B] to-[#4B0C14] ring-1 ring-black/5 shadow-xl flex items-center justify-center overflow-hidden">
                        <div class="absolute -top-10 -left-10 h-40 w-40 rounded-full bg-[#F5B301]/20 blur-2xl"></div>
                        <div class="absolute -bottom-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                        <svg viewBox="0 0 200 200" class="relative w-3/5 h-3/5" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M100 20 172 55v90L100 180 28 145V55L100 20Z" fill="#F5B301"/>
                            <path d="M100 20 172 55 100 90 28 55 100 20Z" fill="#FDCE4E"/>
                            <path d="M100 90v90L28 145V55l72 35Z" fill="#E0A400"/>
                            <path d="M100 90v90l72-35V55l-72 35Z" fill="#F5B301"/>
                            <path d="M64 37.5 136 72.5" stroke="#7A1120" stroke-width="4" stroke-linecap="round"/>
                            <rect x="86" y="86" width="28" height="28" rx="4" fill="#7A1120"/>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== NOSOTROS / CTA ===================== --}}
        <section id="nosotros" class="mx-auto max-w-7xl px-6 lg:px-8 py-24">
            <div class="relative overflow-hidden rounded-3xl bg-[#7A1120]">
                <div class="absolute inset-0 bg-gradient-to-br from-[#8C1D2B] via-[#7A1120] to-[#4B0C14]"></div>
                <div class="absolute -top-16 -right-10 h-64 w-64 rounded-full bg-[#F5B301]/20 blur-3xl"></div>
                <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-10 items-center px-8 py-16 sm:px-14 sm:py-20">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-[#F5C948]">Nuestra promesa</span>
                        <h2 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-white">
                            ¿Listo para tu próximo viaje?
                        </h2>
                        <p class="mt-4 max-w-md text-white/80">
                            Miles de familias confían en Merlo Transportes para llegar seguras a su destino. Reserva hoy y viaja con la tranquilidad que mereces.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row lg:justify-end gap-4">
                        @auth
                            <a href="#buscar" class="inline-flex items-center justify-center gap-2 rounded-full bg-[#F5B301] px-7 py-3.5 text-sm font-bold text-[#2B1113] shadow-lg shadow-black/20 hover:bg-[#E0A400] transition-colors">
                                Comprar boleto ahora
                            </a>
                        @else
                            <button type="button" class="js-modal-trigger inline-flex items-center justify-center gap-2 rounded-full bg-[#F5B301] px-7 py-3.5 text-sm font-bold text-[#2B1113] shadow-lg shadow-black/20 hover:bg-[#E0A400] transition-colors" data-auth-modal="login">
                                Comprar boleto ahora
                            </button>
                        @endauth
                        <a href="#contacto" class="inline-flex items-center justify-center gap-2 rounded-full bg-white/10 px-7 py-3.5 text-sm font-bold text-white ring-1 ring-white/25 hover:bg-white/20 transition-colors">
                            Hablar con nosotros
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== COTIZA TU VIAJE (WHATSAPP) ===================== --}}
        @php
            $whatsappDigits = $setting->whatsappDigits();
        @endphp
        <section id="cotizar" class="mx-auto max-w-7xl px-6 lg:px-8 pb-24">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#8C1D2B] via-[#7A1120] to-[#4B0C14]">
                <div class="absolute -top-16 -left-10 h-64 w-64 rounded-full bg-[#F5B301]/20 blur-3xl"></div>
                <div class="absolute -bottom-16 -right-10 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                <div class="relative flex flex-col sm:flex-row items-center justify-between gap-8 px-8 py-12 sm:px-14 sm:py-14 text-center sm:text-left">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-[#F5C948]">¿Viaje especial o en grupo?</span>
                        <h2 class="mt-3 font-[Poppins] text-2xl sm:text-3xl font-extrabold text-white">Cotiza tu viaje por WhatsApp</h2>
                        <p class="mt-3 max-w-md text-white/80">Cuéntanos los detalles y te respondemos directo por WhatsApp con tu cotización.</p>
                    </div>
                    <button type="button" class="js-modal-trigger shrink-0 inline-flex items-center justify-center gap-2.5 rounded-full bg-[#25D366] px-8 py-3.5 text-sm font-bold text-white shadow-lg shadow-black/20 hover:bg-[#1DA851] transition-colors" data-auth-modal="cotizar">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.001 2C5.583 2 2 5.583 2 10.001c0 1.517.424 2.982 1.226 4.253L2 18l3.851-1.207A7.958 7.958 0 0010 18c4.418 0 8-3.582 8-7.999C18 5.583 14.419 2 10.001 2zm0 14.4a6.36 6.36 0 01-3.397-.978l-.243-.148-2.53.793.8-2.469-.163-.253A6.358 6.358 0 013.6 10c0-3.529 2.87-6.4 6.401-6.4 3.529 0 6.399 2.871 6.399 6.4 0 3.53-2.87 6.4-6.399 6.4z"/></svg>
                        Cotizar
                    </button>
                </div>
            </div>
        </section>

        {{-- ===================== FOOTER / CONTACTO ===================== --}}
        <footer id="contacto" class="bg-[#2B1113] text-white/70">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-14">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-8">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-9 w-auto brightness-0 invert">
                        <span class="hidden sm:block h-8 w-px bg-white/10"></span>
                        <p class="hidden sm:block text-sm text-white/50">Conectando ciudades desde hace más de dos décadas.</p>
                    </div>

                    <ul class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
                        <li><a href="#rutas" class="hover:text-white transition-colors">Rutas</a></li>
                        <li><a href="#paqueteria" class="hover:text-white transition-colors">Paquetería</a></li>
                        <li><a href="#cotizar" class="hover:text-white transition-colors">Cotizar</a></li>
                        <li><a href="#nosotros" class="hover:text-white transition-colors">Nosotros</a></li>
                        <li><a href="tel:8006375600" class="hover:text-white transition-colors">800 637 56 00</a></li>
                        <li><a href="mailto:atencion@merlotransportes.com" class="hover:text-white transition-colors">atencion@merlotransportes.com</a></li>
                    </ul>

                    <div class="flex items-center gap-3">
                        @if ($setting->facebook_url)
                            <a href="{{ $setting->facebook_url }}" target="_blank" rel="noopener" aria-label="Facebook" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 hover:bg-[#8C1D2B] transition-colors">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M20 10c0-5.523-4.477-10-10-10S0 4.477 0 10c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V10h2.54V7.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V10h2.773l-.443 2.89h-2.33v6.988C16.343 19.128 20 14.991 20 10z" clip-rule="evenodd"/></svg>
                            </a>
                        @endif
                        @if ($setting->instagram_url)
                            <a href="{{ $setting->instagram_url }}" target="_blank" rel="noopener" aria-label="Instagram" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 hover:bg-[#8C1D2B] transition-colors">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3c-1.902 0-2.14.008-2.886.042-.746.034-1.255.153-1.7.327a3.43 3.43 0 00-1.24.807 3.43 3.43 0 00-.808 1.24c-.174.445-.293.954-.327 1.7C3.008 7.86 3 8.098 3 10s.008 2.14.042 2.886c.034.746.153 1.255.327 1.7.174.446.408.82.808 1.24.42.4.795.634 1.24.808.445.174.954.293 1.7.327C7.86 16.992 8.098 17 10 17s2.14-.008 2.886-.042c.746-.034 1.255-.153 1.7-.327a3.43 3.43 0 001.24-.808c.4-.42.634-.795.808-1.24.174-.445.293-.954.327-1.7.034-.746.042-.984.042-2.886s-.008-2.14-.042-2.886c-.034-.746-.153-1.255-.327-1.7a3.43 3.43 0 00-.808-1.24 3.43 3.43 0 00-1.24-.808c-.445-.174-.954-.293-1.7-.327C12.14 3.008 11.902 3 10 3zm0 1.802c1.87 0 2.09.008 2.828.04.682.032 1.053.146 1.3.243.327.127.56.279.805.524.244.245.396.478.523.805.097.247.21.618.243 1.3.032.738.04.958.04 2.827s-.008 2.09-.04 2.827c-.032.682-.146 1.053-.243 1.3a2.17 2.17 0 01-.524.805c-.245.244-.478.396-.805.523-.247.097-.618.21-1.3.243-.738.032-.958.04-2.827.04s-2.09-.008-2.827-.04c-.682-.032-1.053-.146-1.3-.243a2.17 2.17 0 01-.805-.524 2.17 2.17 0 01-.524-.805c-.096-.247-.21-.618-.243-1.3-.032-.737-.04-.958-.04-2.827s.008-2.09.04-2.827c.033-.682.147-1.053.243-1.3.128-.327.28-.56.524-.805.245-.245.478-.397.805-.524.247-.097.618-.21 1.3-.243.738-.032.958-.04 2.827-.04zM10 7.135a2.865 2.865 0 100 5.73 2.865 2.865 0 000-5.73zM10 11a1 1 0 110-2 1 1 0 010 2zm3.665-4.865a.67.67 0 100-1.34.67.67 0 000 1.34z" clip-rule="evenodd"/></svg>
                            </a>
                        @endif
                        @if ($whatsappDigits)
                            <a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener" aria-label="WhatsApp" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 hover:bg-[#8C1D2B] transition-colors">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.001 2C5.583 2 2 5.583 2 10.001c0 1.517.424 2.982 1.226 4.253L2 18l3.851-1.207A7.958 7.958 0 0010 18c4.418 0 8-3.582 8-7.999C18 5.583 14.419 2 10.001 2zm0 14.4a6.36 6.36 0 01-3.397-.978l-.243-.148-2.53.793.8-2.469-.163-.253A6.358 6.358 0 013.6 10c0-3.529 2.87-6.4 6.401-6.4 3.529 0 6.399 2.871 6.399 6.4 0 3.53-2.87 6.4-6.399 6.4z"/></svg>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-8 border-t border-white/10 pt-6 text-center sm:text-left">
                    <p class="text-xs text-white/40">&copy; {{ date('Y') }} Merlo Transportes. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>

        @guest
            @include('partials.auth-modals')
        @endguest

        @include('partials.quote-modal', ['whatsappDigits' => $whatsappDigits])
    </body>
</html>
