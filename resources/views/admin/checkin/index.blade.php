<x-admin-layout active="checkin" title="Check-in de viaje">
    @if (session('success'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#8C1D2B]">Check-in</p>
            <h2 class="mt-1 font-[Poppins] text-2xl font-bold text-[#2B1113]">
                @if ($trip)
                    {{ $trip->from }} <span class="text-[#2B1113]/40">→</span> {{ $trip->to }}
                @else
                    Sin viaje activo
                @endif
            </h2>
            @if ($trip)
                <p class="mt-1 text-sm text-[#2B1113]/60">
                    {{ $trip->day?->format('d/m/Y') ?? 'Sin fecha' }}
                    &middot; {{ $trip->departure_time_formatted ?? 'Sin horario' }}
                    &middot; {{ $trip->duration }}
                    @if ($isShowingToday)
                        <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Hoy
                        </span>
                    @endif
                </p>
            @else
                <p class="mt-1 text-sm text-[#2B1113]/60">No hay viajes configurados para hoy ni para días anteriores.</p>
            @endif
        </div>
        @if ($recentTrips->isNotEmpty())
            <form method="GET" action="{{ route('admin.checkin.index') }}" class="flex items-center gap-2">
                <label class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Viaje</label>
                <select name="trip" onchange="this.form.submit()" class="rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @foreach ($recentTrips as $rt)
                        <option value="{{ $rt->id }}" @selected($trip?->id === $rt->id)>
                            {{ $rt->day?->format('d/m') ?? '—' }} ·
                            {{ substr($rt->from, 0, 8) }} → {{ substr($rt->to, 0, 8) }}
                            · {{ $rt->departure_time_formatted ?? '—' }}
                            @if ($rt->day && $rt->day->isSameDay(today())) (Hoy) @endif
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    {{-- ============================================================
         Escanear boleto: cámara (QR) o teclear/pegar el código a mano
         (p. ej. con un lector de código de barras USB/Bluetooth, que
         escribe como si fuera un teclado). Lleva directo al detalle del
         boleto, donde viven los botones reales de "Registrar salida" /
         "Registrar regreso".
         ============================================================ --}}
    <div
        x-data="{
            scanning: false,
            scanner: null,
            scanError: null,
            code: '',
            extractCode(scanned) {
                try {
                    const url = new URL(scanned);
                    const segments = url.pathname.split('/').filter(Boolean);
                    return segments[segments.length - 1] ?? scanned;
                } catch {
                    return scanned;
                }
            },
            async startScan() {
                this.scanError = null;
                if (! window.QrScanner) {
                    this.scanError = 'El escáner no cargó correctamente. Recarga la página.';
                    return;
                }
                this.scanning = true;
                await this.$nextTick();
                this.scanner = new window.QrScanner(
                    this.$refs.scannerVideo,
                    (result) => {
                        window.location = {{ json_encode(route('admin.checkin.scan', ['code' => '__CODE__'])) }}.replace('__CODE__', encodeURIComponent(this.extractCode(result.data)));
                    },
                    { returnDetailedScanResult: true, highlightScanRegion: true, highlightCodeOutline: true },
                );
                try {
                    await this.scanner.start();
                } catch (error) {
                    this.scanError = 'No pudimos acceder a la cámara. Revisa los permisos del navegador.';
                    this.stopScan();
                }
            },
            stopScan() {
                this.scanner?.stop();
                this.scanner?.destroy();
                this.scanner = null;
                this.scanning = false;
            },
        }"
        class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm"
    >
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Escanear boleto</h3>
        <p class="mt-1 text-xs text-[#2B1113]/60">Escanea el código QR del boleto, o escribe/pega el código a mano, para abrir su detalle y registrar salida o regreso.</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('admin.checkin.lookup') }}" class="flex flex-1 min-w-[16rem] items-center gap-2">
                @csrf
                <input type="text" name="code" x-model="code" placeholder="Código del boleto" class="flex-1 rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                <button type="submit" class="shrink-0 rounded-xl bg-[#8C1D2B] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#6F1622] transition-colors">Buscar</button>
            </form>
            <button type="button" @click="scanning ? stopScan() : startScan()" class="inline-flex items-center gap-1.5 rounded-xl bg-[#8C1D2B]/10 px-4 py-2.5 text-sm font-bold text-[#8C1D2B] hover:bg-[#8C1D2B]/15 transition-colors">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M4 2a1 1 0 000 2h1v1a1 1 0 002 0V4h1a1 1 0 100-2H4zM4 16a1 1 0 100 2h3a1 1 0 100-2H5v-1a1 1 0 10-2 0v1zM16 2a1 1 0 010 2h-1v1a1 1 0 11-2 0V4h-1a1 1 0 110-2h4zM16 18a1 1 0 000-2h-1v-1a1 1 0 10-2 0v1h-1a1 1 0 100 2h4zM7 8a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H8a1 1 0 01-1-1V8z"/></svg>
                <span x-text="scanning ? 'Cancelar' : 'Escanear con cámara'"></span>
            </button>
        </div>

        <div x-show="scanning" x-cloak class="mt-4 overflow-hidden rounded-xl bg-black">
            <video x-ref="scannerVideo" class="aspect-square w-full max-w-xs mx-auto"></video>
        </div>
        <p x-show="scanError" x-cloak x-text="scanError" class="mt-2 text-xs font-semibold text-red-600"></p>
    </div>
    @vite(['resources/js/package-scanner.js'])

    @if (! $trip)
        <div class="rounded-3xl bg-white p-8 ring-1 ring-black/5 shadow-sm">
            <p class="text-sm text-[#2B1113]/60">
                Crea un viaje desde <a href="{{ route('admin.viajes') }}" class="font-semibold text-[#8C1D2B] hover:underline">Viajes</a> para empezar a registrar check-ins.
            </p>
        </div>
    @else
        {{-- ============================================================
             Resumen del día: contadores por estado
             ============================================================ --}}
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @php
                $total = $reservations->count();
                $pending = $pendingBoarding->count();
                $returnP = $returnPending->count();
                $done = $fullyDone->count();
            @endphp
            <div class="rounded-2xl bg-white p-4 ring-1 ring-black/5 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Total boletos</p>
                <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-[#2B1113]">{{ $total }}</p>
            </div>
            <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Pendientes abordar</p>
                <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-amber-800">{{ $pending }}</p>
            </div>
            <div class="rounded-2xl bg-blue-50 p-4 ring-1 ring-blue-200 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Pendientes regreso</p>
                <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-blue-800">{{ $returnP }}</p>
            </div>
            <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Ya finalizados</p>
                <p class="mt-1 font-[Poppins] text-2xl font-extrabold text-emerald-800">{{ $done }}</p>
            </div>
        </div>

        {{-- ============================================================
             Pendientes de abordar (la sección principal del operador)
             ============================================================ --}}
        <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Pendientes de abordar</h3>
                    <p class="mt-1 text-xs text-[#2B1113]/60">Pasajeros que aún no suben al autobús. Escanea su boleto o toca "Ver detalles" para registrar la salida (y el regreso si aplica).</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">{{ $pending }}</span>
            </div>

            @if ($pendingBoarding->isEmpty())
                <p class="mt-4 text-sm text-[#2B1113]/50">No hay pasajeros pendientes de abordar. ✓</p>
            @else
                @php $pendingByCustomer = $pendingBoarding->groupBy(fn ($r) => $r->customer_phone ?: $r->customer_display_name); @endphp
                <div class="divide-y divide-black/5">
                    @foreach ($pendingByCustomer as $seats)
                        @php $first = $seats->first(); @endphp
                        <div class="py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $first->customer_display_name }}</p>
                                    @if ($first->customer_display_email)
                                        <p class="text-[11px] text-[#2B1113]/50 break-all">{{ $first->customer_display_email }}</p>
                                    @endif
                                    @if ($first->customer_phone)
                                        <p class="mt-1 text-[11px] text-[#2B1113]/60">📞 {{ $first->customer_phone }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full bg-[#FFFBF6] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#2B1113]/60 ring-1 ring-black/10">{{ $seats->count() }} asiento{{ $seats->count() === 1 ? '' : 's' }}</span>
                            </div>

                            <ul class="mt-3 divide-y divide-black/5 rounded-xl bg-[#FFFBF6] ring-1 ring-black/5">
                                @foreach ($seats as $r)
                                    <li class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-12 sm:items-center">
                                        <div class="sm:col-span-4">
                                            <p class="inline-flex items-center gap-2 rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">
                                                Asiento <span class="font-mono text-sm text-[#8C1D2B]">{{ $r->seat?->label ?? '—' }}</span>
                                            </p>
                                            <p class="mt-1 text-[11px] text-[#2B1113]/60">{{ $r->trip_type_label }}</p>
                                        </div>
                                        <div class="sm:col-span-4">
                                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/50">Precio</p>
                                            <p class="text-sm font-bold text-[#2B1113]">{{ $r->display_price_label }} MXN</p>
                                            <p class="font-mono text-[10px] text-[#2B1113]/40 break-all">{{ $r->ticket_code }}</p>
                                        </div>
                                        <div class="sm:col-span-4 flex sm:justify-end">
                                            <a href="{{ route('admin.checkin.scan', $r->ticket_code) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#6F1622] transition-colors">
                                                Ver detalles
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============================================================
             Pendientes de regreso (solo si hay boletos redondos)
             ============================================================ --}}
        @if ($returnPending->isNotEmpty())
            <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Pendientes de regreso</h3>
                        <p class="mt-1 text-xs text-[#2B1113]/60">Ya abordaron de ida pero aún no regresan. Escanea su boleto o toca "Ver detalles" para registrar el regreso.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">{{ $returnPending->count() }}</span>
                </div>
                @php $returnByCustomer = $returnPending->groupBy(fn ($r) => $r->customer_phone ?: $r->customer_display_name); @endphp
                <div class="divide-y divide-black/5">
                    @foreach ($returnByCustomer as $seats)
                        @php $first = $seats->first(); @endphp
                        <div class="py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $first->customer_display_name }}</p>
                                    @if ($first->customer_display_email)
                                        <p class="text-[11px] text-[#2B1113]/50 break-all">{{ $first->customer_display_email }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full bg-[#FFFBF6] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#2B1113]/60 ring-1 ring-black/10">{{ $seats->count() }} asiento{{ $seats->count() === 1 ? '' : 's' }}</span>
                            </div>

                            <ul class="mt-3 divide-y divide-black/5 rounded-xl bg-[#FFFBF6] ring-1 ring-black/5">
                                @foreach ($seats as $r)
                                    <li class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-12 sm:items-center">
                                        <div class="sm:col-span-4">
                                            <p class="inline-flex items-center gap-2 rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">
                                                Asiento <span class="font-mono text-sm text-[#8C1D2B]">{{ $r->seat?->label ?? '—' }}</span>
                                            </p>
                                        </div>
                                        <div class="sm:col-span-4">
                                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/50">Salida registrada</p>
                                            <p class="text-sm font-bold text-[#2B1113]">{{ $r->outbound_verified_at?->format('d/m/Y H:i') ?? '—' }} hrs</p>
                                            <p class="text-[10px] text-[#2B1113]/60">por {{ $r->outboundVerifiedBy?->name ?? 'operador' }}</p>
                                        </div>
                                        <div class="sm:col-span-4 flex sm:justify-end">
                                            <a href="{{ route('admin.checkin.scan', $r->ticket_code) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#6F1622] transition-colors">
                                                Ver detalles
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============================================================
             Histórico del viaje (ya abordados / ya regresaron)
             ============================================================ --}}
        <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Histórico del viaje</h3>
                    <p class="mt-1 text-xs text-[#2B1113]/60">Pasajeros ya finalizados en este viaje (abordaron y, si aplica, regresaron).</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ $fullyDone->count() }}</span>
            </div>

            @if ($fullyDone->isEmpty())
                <p class="mt-4 text-sm text-[#2B1113]/50">Aún no hay pasajeros finalizados en este viaje.</p>
            @else
                <ul class="divide-y divide-black/5">
                    @foreach ($fullyDone as $r)
                        <li class="grid grid-cols-1 gap-2 py-3 text-sm sm:grid-cols-12 sm:items-center">
                            <div class="sm:col-span-4">
                                <p class="font-[Poppins] font-bold text-[#2B1113]">
                                    {{ $r->customer_display_name }}
                                    <svg class="ml-1 inline h-4 w-4 align-middle text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-8 8a1 1 0 01-1.42 0l-4-4a1 1 0 011.42-1.42L8 12.585l7.296-7.295a1 1 0 011.408 0z" clip-rule="evenodd"/></svg>
                                </p>
                                @if ($r->customer_display_email)
                                    <p class="text-[11px] text-[#2B1113]/50 break-all">{{ $r->customer_display_email }}</p>
                                @endif
                            </div>
                            <div class="sm:col-span-2">
                                <p class="inline-flex items-center gap-1.5 rounded-lg bg-[#FFFBF6] px-2 py-1 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">
                                    Asiento <span class="font-mono text-[#8C1D2B]">{{ $r->seat?->label ?? '—' }}</span>
                                </p>
                            </div>
                            <div class="sm:col-span-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Salida</p>
                                <p class="text-xs text-[#2B1113]">{{ $r->outbound_verified_at?->format('d/m H:i') ?? '—' }} &middot; {{ $r->outboundVerifiedBy?->name ?? 'operador' }}</p>
                                @if ($r->needsBothLegs() || $r->isReturnLeg())
                                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Regreso</p>
                                    <p class="text-xs text-[#2B1113]">{{ $r->return_verified_at?->format('d/m H:i') ?? '—' }} &middot; {{ $r->returnVerifiedBy?->name ?? 'operador' }}</p>
                                @endif
                            </div>
                            <div class="sm:col-span-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Ticket</p>
                                <p class="font-mono text-[10px] text-[#2B1113]/60 break-all">{{ $r->ticket_code }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-admin-layout>