<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Rastrea tu paquete · Merlo Transportes</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FFFBF6] text-[#2B1113] antialiased">
        @php
            $statusMeta = [
                'recolectado' => ['label' => 'Recolectado'],
                'en_recorrido' => ['label' => 'En recorrido'],
                'entregado' => ['label' => 'Entregado'],
                'no_entregado' => ['label' => 'No entregado'],
            ];
        @endphp

        <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-black/5">
            <div class="mx-auto max-w-4xl px-6 lg:px-8">
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

        <main class="mx-auto max-w-2xl px-6 py-16 lg:px-8">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#8C1D2B]">Paquetería</p>
                <h1 class="mt-3 font-[Poppins] text-3xl sm:text-4xl font-extrabold text-[#2B1113]">Rastrea tu paquete</h1>
                <p class="mt-3 text-[#2B1113]/60">Escribe el código de rastreo que te enviamos por correo.</p>
            </div>

            <form method="GET" action="{{ route('paqueteria.rastreo') }}" class="mt-8 flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="code"
                    value="{{ $code }}"
                    placeholder="Ej. MP-4F7K9XQR"
                    class="flex-1 rounded-xl border-black/10 uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case focus:border-[#8C1D2B] focus:ring-[#8C1D2B]"
                >
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/25 hover:bg-[#6F1622] transition-colors">
                    Rastrear
                </button>
            </form>

            @if ($notFound)
                <div class="mt-10 rounded-2xl border border-dashed border-black/10 bg-white p-8 text-center shadow-sm">
                    <p class="font-semibold text-[#2B1113]">No encontramos ese código.</p>
                    <p class="mt-1 text-sm text-[#2B1113]/60">Revisa que esté escrito tal como aparece en tu correo de confirmación.</p>
                </div>
            @endif

            @if ($package)
                <div class="mt-10 rounded-2xl bg-white p-6 sm:p-8 shadow-sm ring-1 ring-black/5">
                    <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Código {{ $package->tracking_code }}</p>
                    @if ($package->isGrouped())
                        <p class="mt-1 text-xs text-[#2B1113]/50">Este paquete es parte de un envío conjunto de {{ $package->group->packages->count() }} paquete(s).</p>
                    @endif

                    <div class="mt-8">
                        <x-package-status-stepper :status="$package->status" />
                    </div>
                </div>
            @endif

            @if ($group)
                <div class="mt-10 space-y-4">
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Envío {{ $group->tracking_code }}</p>
                        <p class="mt-1 text-sm text-[#2B1113]/60">{{ $group->packages->count() }} paquete(s) en este envío.</p>
                        @if ($groupFailedCount > 0)
                            <p class="mt-1 text-xs font-semibold text-red-600">{{ $groupFailedCount }} paquete{{ $groupFailedCount === 1 ? '' : 's' }} no se pudo entregar.</p>
                        @endif

                        <div class="mt-6">
                            <x-package-status-stepper :status="$groupStatus" />
                        </div>
                    </div>

                    @foreach ($group->packages as $member)
                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5 flex items-center justify-between gap-4">
                            <p class="w-28 shrink-0 text-sm font-bold text-[#2B1113]">{{ $member->tracking_code }}</p>
                            <div class="flex-1">
                                <x-package-status-stepper :status="$member->status" compact />
                            </div>
                            <span class="shrink-0 text-xs font-bold {{ $member->status === 'no_entregado' ? 'text-red-600' : 'text-[#2B1113]/60' }}">
                                {{ $statusMeta[$member->status]['label'] ?? $member->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </main>
    </body>
</html>
