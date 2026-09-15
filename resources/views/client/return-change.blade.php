@php
    $original = $reservation->landingRoute;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Elegir nueva fecha de regreso · Merlo Transportes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FFFBF6] text-[#2B1113] antialiased">

    <header class="border-b border-black/5 bg-white">
        <div class="mx-auto flex h-20 max-w-2xl items-center px-6">
            <a href="{{ route('cliente.boletos.ver', $reservation) }}" class="flex items-center gap-2 text-sm font-bold text-[#8C1D2B] hover:underline">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd"/></svg>
                Volver a tu boleto
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-6 py-10">
        @if (session('error'))
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
        @endif

        <div class="mb-6">
            <h1 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Elige tu nueva fecha de regreso</h1>
            <p class="mt-1 text-sm text-[#2B1113]/60">Viajes de {{ $original->to }} a {{ $original->from }} disponibles. Tu regreso original era el {{ $original->return_date?->format('d/m/Y') ?? '—' }}.</p>
        </div>

        <div class="mb-6 rounded-2xl border border-[#F5B301]/40 bg-[#F5B301]/10 px-4 py-3 text-sm text-[#2B1113]">
            <strong>Importante:</strong> al elegir una fecha, se te asignará un asiento distinto al original según la disponibilidad de ese viaje. No se cobra ni se regresa dinero. Tienes hasta el <strong>{{ $reservation->return_change_deadline->format('d/m/Y H:i') }}</strong> para confirmar.
        </div>

        @forelse ($options as $option)
            <div class="mb-3 flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-[Poppins] text-lg font-extrabold text-[#2B1113]">{{ $option->day?->format('d/m/Y') ?? '—' }}</p>
                    <p class="mt-0.5 text-xs text-[#2B1113]/60">{{ $option->from }} &rarr; {{ $option->to }} &middot; {{ $option->departure_time_formatted ?? '—' }} &middot; {{ $option->available_seats }} asiento(s) disponible(s)</p>
                </div>
                <form method="POST" action="{{ route('cliente.boletos.return-change.confirm', $reservation) }}" onsubmit="return confirm('Se te asignará un asiento distinto al original en este viaje, sujeto a disponibilidad. ¿Confirmar esta fecha?');">
                    @csrf
                    <input type="hidden" name="landing_route_id" value="{{ $option->id }}">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                        Elegir esta fecha
                    </button>
                </form>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[#8C1D2B]/30 bg-white px-5 py-8 text-center">
                <p class="text-sm font-semibold text-[#2B1113]">No hay viajes disponibles todavía para esta ruta.</p>
                <p class="mt-1 text-xs text-[#2B1113]/60">Vuelve a intentarlo más tarde, antes de que venza tu plazo.</p>
            </div>
        @endforelse
    </main>
</body>
</html>
