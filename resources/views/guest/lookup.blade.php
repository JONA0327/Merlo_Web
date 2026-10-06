<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis boletos · Merlo Transportes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FFFBF6] text-[#2B1113] antialiased min-h-screen">
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-black/5">
        <div class="mx-auto max-w-2xl px-6 lg:px-2">
            <div class="flex h-20 items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-11 w-auto">
                </a>
                <a href="{{ url('/') }}" class="text-xs font-bold text-[#8C1D2B] hover:text-[#6F1622]">
                    ← Volver al inicio
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-6 py-12">
        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#8C1D2B]">Mis boletos</p>
        <h1 class="mt-2 font-[Poppins] text-3xl font-extrabold text-[#2B1113]">Encuentra tus boletos</h1>
        <p class="mt-2 text-[#2B1113]/60">
            ¿Compraste sin iniciar sesión? Escribe el <strong>nombre</strong> y <strong>teléfono</strong> que usaste al pagar — te mostramos los viajes asociados para que puedas verlos cuando los necesites.
        </p>

        @if (session('error'))
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('guest.tickets.lookup.search') }}" class="mt-8 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            @csrf
            <div class="space-y-4">
                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Nombre completo</span>
                    <input type="text" name="customer_name" required minlength="2" maxlength="120" value="{{ old('customer_name', $name) }}" placeholder="Como aparece en tu boleto" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @error('customer_name') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Teléfono</span>
                    <input type="tel" name="customer_phone" required minlength="7" maxlength="30" value="{{ old('customer_phone', $phone) }}" placeholder="444 123 4567" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    <p class="mt-1 text-[10px] text-[#2B1113]/40">Acepta 4441234567, 444 123 4567 o +52 444 123 4567.</p>
                    @error('customer_phone') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </label>
            </div>
            <button type="submit" class="mt-5 w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                Buscar mis boletos
            </button>
        </form>

        @if ($reservations !== null)
            <div class="mt-8">
                @if ($reservations->isEmpty())
                    <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm text-center">
                        <p class="text-sm text-[#2B1113]/70">
                            No encontramos boletos para <strong>{{ $name }}</strong> con ese teléfono en los últimos 6 meses. Verifica que el nombre y teléfono coincidan con los que usaste al pagar.
                        </p>
                    </div>
                @else
                    <p class="mb-3 text-sm font-semibold text-[#2B1113]">
                        {{ $reservations->count() }} {{ $reservations->count() === 1 ? 'boleto encontrado' : 'boletos encontrados' }}
                    </p>
                    <ul class="space-y-3">
                        @foreach ($reservations as $r)
                            @php
                                $trip = $r->landingRoute;
                                $methodLabel = $r->payment_method_label ?? '—';
                                $statusLabel = match ($r->payment_status) {
                                    'completed' => 'Pagado',
                                    'pending' => $r->isCash() ? 'RESERVADO · paga en ventanilla' : 'Pendiente de validar',
                                    'failed' => 'Pago fallido',
                                    'refunded' => 'Reembolsado',
                                    'chargeback' => 'Contracargo',
                                    default => $r->payment_status ?? '—',
                                };
                                $statusClass = match ($r->payment_status) {
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <li class="rounded-3xl bg-white p-5 ring-1 ring-black/5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-[Poppins] text-lg font-bold text-[#2B1113]">
                                            {{ $trip?->from ?? '—' }} <span class="text-[#2B1113]/40">→</span> {{ $trip?->to ?? '—' }}
                                        </p>
                                        <p class="mt-1 text-xs text-[#2B1113]/60">
                                            {{ $trip?->day?->format('d/m/Y') ?? '—' }}
                                            @if ($trip?->departure_time_formatted)
                                                · {{ $trip->departure_time_formatted }}
                                            @endif
                                            · {{ $r->trip_type_label }}
                                            @if ($r->seat?->label)
                                                · Asiento <span class="font-mono text-[#8C1D2B]">{{ $r->seat->label }}</span>
                                            @endif
                                        </p>
                                        <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-[#FFFBF6] px-2.5 py-0.5 font-bold text-[#2B1113] ring-1 ring-black/10">
                                                {{ $methodLabel }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-bold {{ $statusClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>
                                        @if ($r->ticket_code)
                                            <p class="mt-2 font-mono text-[10px] text-[#2B1113]/50 break-all">Ticket: {{ $r->ticket_code }}</p>
                                        @endif
                                    </div>
                                    <a href="{{ route('travel.payment.pending', $r) }}" class="shrink-0 inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-4 py-2.5 text-xs font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                                        Ver boleto
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <p class="mt-10 text-center text-xs text-[#2B1113]/50">
            ¿Dudas? Escríbenos a <a href="mailto:soporte@merlo.com.mx" class="text-[#8C1D2B] hover:underline">soporte@merlo.com.mx</a>
        </p>
    </main>
</body>
</html>