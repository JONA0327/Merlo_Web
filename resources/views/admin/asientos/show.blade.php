<x-admin-layout :active="'asientos'" :title="'Apartar asientos — '.$trip->from.' → '.$trip->to">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.asientos.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#8C1D2B] hover:text-[#6F1622]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17.5 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h11.19A.75.75 0 0117.5 10z" clip-rule="evenodd"/></svg>
                Volver a la lista de viajes
            </a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">{{ $trip->from }} → {{ $trip->to }}</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">
                {{ $trip->day ? $trip->day->format('d/m/Y') : 'Sin fecha' }} ·
                {{ $trip->departure_time_formatted ?? 'Sin horario' }} ·
                {{ $trip->duration }} ·
                {{ $trip->formatted_price }}
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold text-[#2B1113]/60">
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-amber-800">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                {{ $pendingCount }} pendiente{{ $pendingCount === 1 ? '' : 's' }}
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-blue-800">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                {{ $sentCount }} enviado{{ $sentCount === 1 ? '' : 's' }}
            </span>
            <a href="{{ route('admin.asientos.manifest', $trip) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-full bg-[#2B1113]/5 px-3 py-1.5 text-[#2B1113] hover:bg-[#2B1113]/10 transition-colors">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.097 1.126.153A2.212 2.212 0 0118 8.653v4.097A2.25 2.25 0 0115.75 15h-.241l.305 1.984A1.75 1.75 0 0113.84 19H6.16a1.75 1.75 0 01-1.973-2.016L4.491 15H4.25A2.25 2.25 0 012 12.75V8.653c0-1.082.775-2.034 1.874-2.198.374-.056.75-.107 1.126-.153V2.75zm8.5 3.397V2.75a.25.25 0 00-.25-.25h-6.5a.25.25 0 00-.25.25v3.397c1.126-.1 2.264-.148 3.5-.148s2.374.049 3.5.148zm-7 9.853l.414-2.691c1.364-.1 2.73-.15 4.086-.15 1.356 0 2.722.05 4.086.15l.414 2.691a.25.25 0 01-.247.287H6.747a.25.25 0 01-.247-.287z" clip-rule="evenodd"/></svg>
                Imprimir lista
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 xl:items-start">
        {{-- ===================== Plano de asientos ===================== --}}
        <div class="xl:col-span-7 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-4 text-[11px] font-semibold text-[#2B1113]/60">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#15803D] bg-white"></span> Disponible</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-2 border-[#16A34A] bg-white"></span> Seleccionado</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#A16207] bg-[#FACC15]"></span> Apartado (pendiente)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#1D4ED8] bg-[#3B82F6]"></span> Boleto enviado</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-[#991B1B] bg-[#EF4444]"></span> Vendido</span>
            </div>

            <div id="admin-seat-canvas" class="overflow-auto rounded-2xl border border-black/10 bg-[#FFFBF6]" style="min-height:560px;"></div>
        </div>

        {{-- ===================== Panel derecho: apartado + lista ===================== --}}
        <div class="xl:col-span-5 space-y-6">
            @if ($trip->hasEnded())
                <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Viaje cerrado</h3>
                    <p class="mt-1 text-xs text-[#2B1113]/60">La fecha de este viaje ya pasó. Puedes seguir viendo los apartados de abajo, pero ya no se pueden crear nuevos apartados, enviar boletos ni editar la disponibilidad de asientos.</p>
                </div>
            @else
            {{-- Form para crear apartado --}}
            <form method="POST" action="{{ route('admin.asientos.store', $trip) }}" id="apartado-form" class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                @csrf

                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Apartar para un cliente</h3>
                <p class="mt-1 text-xs text-[#2B1113]/60">Selecciona uno o varios asientos en el plano y captura los datos del cliente. Al guardar, el boleto se envía <strong>automáticamente por WhatsApp</strong>.</p>

                @php
                    $tripTypeLabels = \App\Models\TripTicketPrice::tripTypes();
                    $zones = $trip->busUnit->seats->pluck('zone')->filter()->unique()->sort()->values();
                @endphp
                <label class="mt-3 block">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Categoría</span>
                    <select name="trip_type" id="admin-trip-type-select" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @foreach ($tripTypeLabels as $type => $label)
                            @php $price = $trip->priceFor($type); @endphp
                            <option value="{{ $type }}" {{ $type === \App\Models\TripTicketPrice::TYPE_ONE_WAY ? 'selected' : '' }}>{{ $label }} — {{ $price ? $price->formatted_price : 'sin precio' }}</option>
                        @endforeach
                    </select>
                </label>

                <div id="admin-zone-picker" class="mt-3 hidden">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Zonas disponibles</span>
                    <p class="mt-0.5 text-[10px] text-[#2B1113]/40">Clic en una zona para seleccionar sus asientos en el plano.</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($zones as $zone)
                            <button type="button" data-zone="{{ $zone }}" class="admin-zone-chip rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#8C1D2B] ring-1 ring-[#8C1D2B]/30 hover:bg-[#8C1D2B]/10 transition-colors">{{ $zone }}</button>
                        @endforeach
                        @if ($zones->isEmpty())
                            <span class="text-[11px] text-[#2B1113]/40">No hay zonas definidas en el editor de esta unidad.</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Nombre del cliente</span>
                        <input type="text" id="admin-apartado-customer-name" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120" placeholder="Ej. María Hernández" list="agenda-clientes-list" autocomplete="off" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        <datalist id="agenda-clientes-list">
                            @foreach ($customers as $c)
                                <option value="{{ $c->name }}">{{ $c->phone }}</option>
                            @endforeach
                        </datalist>
                        <p class="mt-1 text-[10px] text-[#2B1113]/40">Elige un nombre de la <a href="{{ route('admin.agenda.index') }}" target="_blank" class="underline">agenda</a> para autollenar teléfono/correo, o escribe uno nuevo.</p>
                        @error('customer_name') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">WhatsApp del cliente</span>
                        <input type="tel" inputmode="numeric" id="admin-apartado-customer-phone" name="customer_phone" value="{{ old('customer_phone') }}" required maxlength="12" placeholder="444 123 4567" class="phone-mx-input mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        <p class="mt-1 text-[10px] text-[#2B1113]/40">A 10 dígitos se le agrega automáticamente el 52 de México. Al guardar el apartado, el boleto se envía automáticamente por WhatsApp a este número.</p>
                        @error('customer_phone') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Correo del cliente (opcional)</span>
                        <input type="email" id="admin-apartado-customer-email" name="customer_email" value="{{ old('customer_email') }}" maxlength="180" placeholder="cliente@correo.com" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        <p class="mt-1 text-[10px] text-[#2B1113]/40">Solo para tenerlo como referencia — el boleto no se manda por correo.</p>
                        @error('customer_email') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">¿Pagado?</span>
                            <select name="paid" id="admin-paid-select" required class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="1" {{ old('paid', '1') === '1' ? 'selected' : '' }}>Sí, ya está pagado</option>
                                <option value="0" {{ old('paid') === '0' ? 'selected' : '' }}>No, pagará después</option>
                            </select>
                            @error('paid') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Método de pago (todos)</span>
                            {{-- Not submitted directly (no name) — it's a quick default
                                 that fills in the per-seat selects below, which is what
                                 actually submits as payment_method[seat_id]. Lets a
                                 2+ seat apartado split across methods, e.g. one seat
                                 cash and another transfer. --}}
                            <select id="admin-payment-method-select" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="transfer">Transferencia</option>
                                <option value="cash">Efectivo</option>
                                <option value="tbd">Por definir</option>
                            </select>
                            @error('payment_method') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                        </label>
                    </div>
                    <p id="admin-payment-hint" class="text-[10px] text-[#2B1113]/40"></p>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#2B1113]/60">Notas (opcional)</span>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Ej. Pagará en efectivo al abordar" class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">{{ old('notes') }}</textarea>
                    </label>
                </div>

                <div id="apartado-selected-summary" class="mt-4 rounded-xl bg-[#FFFBF6] p-3 text-xs text-[#2B1113]/60">
                    Clic en el plano para seleccionar asientos.
                </div>

                <div id="apartado-hidden-inputs"></div>

                <button type="submit" id="apartado-submit" disabled class="mt-4 w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors disabled:cursor-not-allowed disabled:opacity-40">
                    Apartar y enviar por WhatsApp
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- ===================== Lista de apartados (abajo del mapa, ancho completo) ===================== --}}
    <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Apartados</h3>

        @if ($reservations->isEmpty())
            <p class="mt-3 text-xs text-[#2B1113]/50">Aún no hay apartados para este viaje.</p>
        @else
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($reservations as $reservation)
                    @php $allSeats = collect([$reservation->seat?->label])->merge($reservation->groupSeats->pluck('seat.label'))->filter(); @endphp
                    <li class="rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $reservation->customer_display_name }}</p>
                                <p class="mt-0.5 text-[11px] text-[#2B1113]/60 break-all">{{ $reservation->customer_display_email ?: '—' }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    @foreach ($allSeats as $seatLabel)
                                        <span class="rounded-md bg-white px-2 py-0.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $seatLabel }}</span>
                                    @endforeach
                                    <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#2B1113]/70 ring-1 ring-black/10">{{ $reservation->trip_type_label }}</span>
                                    <span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $reservation->isPending() ? 'bg-amber-200 text-amber-900' : ($reservation->isSent() ? 'bg-blue-200 text-blue-900' : 'bg-slate-200 text-slate-700') }}">
                                        {{ $reservation->status }}
                                    </span>
                                </div>
                                @if ($allSeats->count() > 1)
                                    <p class="mt-1.5 text-[10px] font-semibold text-[#2B1113]/50">{{ $allSeats->count() }} asientos · se envían juntos en una sola imagen</p>
                                @endif
                                @if ($reservation->ticket_sent_at)
                                    <p class="mt-1.5 text-[10px] font-semibold text-[#2B1113]/50">Enviado {{ $reservation->ticket_sent_at->diffForHumans() }}</p>
                                @endif
                                @if ($reservation->notes)
                                    <p class="mt-1.5 text-[11px] italic text-[#2B1113]/60">{{ $reservation->notes }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                @if (! $reservation->ticket_sent_at && ! $trip->hasEnded())
                                    <form method="POST" action="{{ route('admin.asientos.send', [$trip, $reservation]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 transition-colors">
                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                            Enviar boleto{{ $allSeats->count() > 1 ? 's' : '' }}
                                        </button>
                                    </form>
                                @endif
                                @if (! $reservation->isFullyCheckedIn() && ! $trip->hasEnded())
                                    <button type="button" class="admin-edit-category-toggle text-[10px] font-semibold text-[#8C1D2B] hover:text-[#6F1622]" data-target="edit-category-{{ $reservation->id }}">Editar</button>
                                @endif
                                @unless ($trip->hasEnded())
                                    <form method="POST" action="{{ route('admin.asientos.destroy', [$trip, $reservation]) }}" class="inline" onsubmit="return confirmDeleteApartado(this, {{ $allSeats->count() }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[10px] font-semibold text-red-600 hover:text-red-700">Borrar</button>
                                    </form>
                                @endunless
                            </div>
                        </div>

                        @if (! $reservation->isFullyCheckedIn() && ! $trip->hasEnded())
                            <div id="edit-category-{{ $reservation->id }}" class="admin-edit-category-panel mt-3 hidden rounded-xl bg-white p-3 ring-1 ring-black/10">
                                <form method="POST" action="{{ route('admin.asientos.update-category', [$trip, $reservation]) }}" class="space-y-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="block">
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-[#2B1113]/60">Categoría</span>
                                            <select name="trip_type" class="mt-1 w-full rounded-lg border border-black/10 bg-[#FFFBF6] px-2 py-1.5 text-xs font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                                @foreach (\App\Models\TripTicketPrice::tripTypes() as $type => $label)
                                                    <option value="{{ $type }}" {{ $reservation->trip_type === $type ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-[#2B1113]/60">Pago</span>
                                            <select name="payment_method" class="mt-1 w-full rounded-lg border border-black/10 bg-[#FFFBF6] px-2 py-1.5 text-xs font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                                <option value="transfer" {{ $reservation->payment_method === 'transfer' ? 'selected' : '' }}>Transfer</option>
                                                <option value="cash" {{ $reservation->payment_method === 'cash' ? 'selected' : '' }}>Efectivo</option>
                                                <option value="tbd" {{ $reservation->payment_method === 'tbd' ? 'selected' : '' }}>Por definir</option>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-[#2B1113]/60">Estado</span>
                                            <select name="payment_status" class="mt-1 w-full rounded-lg border border-black/10 bg-[#FFFBF6] px-2 py-1.5 text-xs font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                                <option value="pending" {{ $reservation->payment_status !== 'completed' ? 'selected' : '' }}>Pendiente</option>
                                                <option value="completed" {{ $reservation->payment_status === 'completed' ? 'selected' : '' }}>Pagado</option>
                                            </select>
                                        </label>
                                    </div>
                                    <p class="text-[10px] text-[#2B1113]/40">Cambiar estado a "Pagado" rellena automáticamente <code class="font-mono">paid_at</code>. El envío por WhatsApp sigue siendo manual — toca "Enviar boleto(s)" cuando quieras mandarlo.</p>
                                    <button type="submit" class="w-full rounded-lg bg-[#8C1D2B] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#6F1622] transition-colors">Guardar cambios</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $reservations->links() }}</div>
        @endif
    </div>

    <script>
        // Agenda de clientes: pick a name already on file and autofill
        // phone/email instead of retyping them (the datalist option's
        // visible text is the phone; the value is the name).
        (function () {
            const customers = @json($customers->map(fn ($c) => ['name' => $c->name, 'phone' => $c->phone, 'email' => $c->email])->values());
            const nameInput = document.getElementById('admin-apartado-customer-name');
            const phoneInput = document.getElementById('admin-apartado-customer-phone');
            const emailInput = document.getElementById('admin-apartado-customer-email');
            if (!nameInput || !phoneInput || !emailInput) return;

            nameInput.addEventListener('input', () => {
                const match = customers.find((c) => c.name === nameInput.value);
                if (match) {
                    phoneInput.value = match.phone;
                    emailInput.value = match.email ?? '';
                }
            });
        })();

        // A plain confirm() was too easy to click through by accident
        // (an admin reported deleting a real apartado this way), so
        // deleting now requires typing the word ELIMINAR into a prompt.
        function confirmDeleteApartado(form, seatCount) {
            const plural = seatCount > 1 ? 's' : '';
            const typed = window.prompt(
                `Vas a BORRAR este apartado${seatCount > 1 ? ` (${seatCount} asientos)` : ''}. `
                + `El${plural ? '' : ' asiento'}${seatCount > 1 ? 's volverán' : ' volverá'} a estar disponible${plural}.\n\n`
                + 'Escribe ELIMINAR para confirmar:'
            );
            return typed !== null && typed.trim().toUpperCase() === 'ELIMINAR';
        }

        document.querySelectorAll('.admin-edit-category-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                const panel = document.getElementById(btn.getAttribute('data-target'));
                if (panel) panel.classList.toggle('hidden');
            });
        });

        (function () {
            const paidSelect = document.getElementById('admin-paid-select');
            const methodSelect = document.getElementById('admin-payment-method-select');
            const hint = document.getElementById('admin-payment-hint');
            if (!paidSelect || !methodSelect || !hint) return;

            function updateHint() {
                const paid = paidSelect.value === '1';
                if (paid) {
                    hint.textContent = 'Se manda el boleto con QR por WhatsApp de inmediato.';
                    return;
                }
                if (methodSelect.value === 'transfer') {
                    hint.textContent = 'Se crea como pendiente con una referencia de transferencia; valídala después en Pagos para mandar el boleto con QR.';
                } else {
                    hint.textContent = 'Se crea como pendiente y solo se manda un aviso de reservación; confirma el pago después en Pagos para imprimir/mandar el boleto con QR.';
                }
            }

            paidSelect.addEventListener('change', updateHint);
            methodSelect.addEventListener('change', updateHint);
            updateHint();
        })();
    </script>

    <script>
        window.__ADMIN_SEAT_PICKER__ = {
            tripId: {{ $trip->id }},
            tripEnded: {{ $trip->hasEnded() ? 'true' : 'false' }},
            unitName: {!! json_encode($trip->busUnit->name) !!},
            canvasWidth: {{ $trip->busUnit->canvas_width }},
            canvasHeight: {{ $trip->busUnit->canvas_height }},
            hasUpperDeck: {{ $trip->busUnit->has_upper_deck ? 'true' : 'false' }},
            seats: {!! json_encode($trip->busUnit->seats->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'kind' => $s->kind,
                'type' => $s->type,
                'deck' => $s->deck,
                'shape' => $s->shape,
                'width' => $s->width,
                'height' => $s->height,
                'corner_radius' => $s->corner_radius,
                'border_width' => $s->border_width,
                'color' => $s->color,
                'allowed_trip_type' => $s->allowed_trip_type,
                'zone' => $s->zone,
                'pos_x' => $s->pos_x,
                'pos_y' => $s->pos_y,
            ])) !!},
            // Map of seat_id → status (pending | sent | null when free).
            // The admin can still pick "free" seats only — pending/sent
            // show as already taken.
            seatStatuses: {!! json_encode($reservationsBySeat->mapWithKeys(fn ($items, $seatId) => [
                $seatId => $items->last()->status,
            ])) !!},
            // The set of seats already taken by a real client purchase
            // (separate from admin apartados). Empty unless the customer
            // flow has been used in this environment.
            takenIds: {!! json_encode($takenIds) !!},
        };
    </script>
    @vite(['resources/js/admin-seat-picker.js'])
</x-admin-layout>
