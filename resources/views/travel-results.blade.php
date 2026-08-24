<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Resultados de viajes</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-black/5">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <a href="/" class="flex items-center gap-3 shrink-0">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-11 w-auto">
                    </a>
                    <a href="/" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                        Volver
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-12 lg:px-8">
            <div class="mb-8">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#8C1D2B]">Disponibilidad</p>
                <h1 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-[#2B1113]">
                    {{ $from ?? 'Origen' }} → {{ $to ?? 'Destino' }}
                </h1>
                @if ($date)
                    <p class="mt-2 text-sm text-[#2B1113]/60">Mostrando viajes a partir del {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}, ordenados por fecha.</p>
                @endif
                @if ($returnDate ?? null)
                    <p class="mt-1 text-sm text-[#2B1113]/60">Regreso deseado: {{ \Illuminate\Support\Carbon::parse($returnDate)->format('d/m/Y') }}</p>
                @endif
            </div>

            @if ($trips->isEmpty())
                <div class="rounded-3xl border border-dashed border-black/10 bg-white p-10 text-center shadow-sm">
                    <h2 class="text-xl font-bold text-[#2B1113]">No hay viajes disponibles</h2>
                    <p class="mt-2 text-[#2B1113]/60">Prueba con otro origen o destino para encontrar más opciones.</p>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($trips as $trip)
                        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <p class="font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $trip->from }}</p>
                                        <svg class="h-5 w-5 text-[#8C1D2B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 10a.75.75 0 01.75-.75h11.19l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.25A.75.75 0 012.5 10z" clip-rule="evenodd"/></svg>
                                        <p class="font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $trip->to }}</p>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-[#2B1113]/70">
                                        <span class="rounded-full bg-[#8C1D2B]/10 px-2.5 py-1 font-semibold text-[#8C1D2B]">{{ $trip->day ? $trip->day->format('d/m/Y') : 'Sin fecha' }}</span>
                                        @if ($trip->return_date)
                                            <span class="rounded-full bg-[#8C1D2B]/10 px-2.5 py-1 font-semibold text-[#8C1D2B]">Regreso: {{ $trip->return_date->format('d/m/Y') }}</span>
                                        @endif
                                        <span class="rounded-full bg-[#8C1D2B]/10 px-2.5 py-1 font-semibold text-[#8C1D2B]">{{ $trip->duration }}</span>
                                        <span>Horario: {{ $trip->departure_time_formatted ?? 'Sin horario' }}</span>
                                        <span>Asientos disponibles: {{ $trip->available_seats ?? 0 }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-5">
                                    <div class="text-right">
                                        <p class="text-xs uppercase tracking-[0.2em] text-[#2B1113]/50">Costo</p>
                                        <p class="font-[Poppins] text-3xl font-extrabold text-[#8C1D2B]">{{ $trip->formatted_price }}</p>
                                    </div>

                                    @if ($trip->hasSeatMap())
                                        <a href="{{ route('travel.seats', $trip) }}" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#6F1622] transition-colors">
                                            Elegir asientos
                                        </a>
                                    @else
                                        <form method="GET" action="{{ route('travel.search') }}" class="flex items-center gap-3">
                                            <input type="hidden" name="from" value="{{ $trip->from }}">
                                            <input type="hidden" name="to" value="{{ $trip->to }}">
                                            <label class="block">
                                                <span class="sr-only">Cantidad de asientos</span>
                                                <select name="seats" class="rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-medium text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                                    @for ($i = 1; $i <= min(6, max(1, $trip->available_seats ?? 1)); $i++)
                                                        <option value="{{ $i }}">{{ $i }} asiento{{ $i > 1 ? 's' : '' }}</option>
                                                    @endfor
                                                </select>
                                            </label>
                                            <button type="button" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#6F1622] transition-colors">
                                                Seleccionar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </main>
    </body>
</html>
