<x-admin-layout active="pagos" title="Detalle de pago">
    @php
        $r = $reservation;
        $trip = $r->landingRoute;
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('admin.pagos.index') }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">← Volver a Pagos</a>
            <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Pago #{{ $r->id }}</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Ticket code: <span class="font-mono">{{ $r->ticket_code }}</span></p>
        </div>
        <div>
            @php
                $statusColors = [
                    'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                    'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
                    'failed' => 'bg-red-50 text-red-700 ring-red-200',
                    'refunded' => 'bg-rose-50 text-rose-700 ring-rose-200',
                    'chargeback' => 'bg-rose-50 text-rose-700 ring-rose-200',
                ];
                $statusLabels = [
                    'completed' => 'Completado',
                    'pending' => 'Pendiente',
                    'failed' => 'Fallido',
                    'refunded' => 'Reembolsado',
                    'chargeback' => 'Contracargo',
                ];
            @endphp
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $statusColors[$r->payment_status] ?? 'bg-zinc-50 text-zinc-700 ring-zinc-200' }}">
                {{ $statusLabels[$r->payment_status] ?? $r->payment_status }}
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- LEFT: charge detail --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="overflow-hidden rounded-3xl bg-white ring-1 ring-black/5 shadow-sm">
                <div class="bg-[#8C1D2B] px-6 py-4 text-white">
                    <p class="font-[Poppins] text-lg font-bold">Detalle del cargo</p>
                    <p class="text-xs opacity-80">OpenPay · {{ $r->payment_method_label }} @if($r->payment_method_detail)· {{ $r->payment_method_detail }}@endif</p>
                </div>
                <dl class="grid grid-cols-2 gap-4 p-6 text-sm">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">OpenPay charge</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-[#2B1113]">{{ $r->openpay_charge_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">OpenPay customer</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-[#2B1113]">{{ $r->openpay_customer_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Autorización</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-[#2B1113]">{{ $r->openpay_authorization ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Método OpenPay</dt>
                        <dd class="mt-1 font-mono text-xs text-[#2B1113]">{{ $r->openpay_payment_method ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Fee OpenPay</dt>
                        <dd class="mt-1 text-sm text-[#2B1113]">${{ number_format($r->openpay_fee ?? 0, 2) }} MXN</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Pagado el</dt>
                        <dd class="mt-1 text-sm text-[#2B1113]">{{ $r->paid_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
                    </div>
                    <div class="col-span-2 border-t border-black/5 pt-4">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Desglose</dt>
                        <dd class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-[#2B1113]/60">Subtotal</span><span>${{ number_format($r->subtotal ?? 0, 2) }} MXN</span></div>
                            <div class="flex justify-between"><span class="text-[#2B1113]/60">IVA (16%)</span><span>${{ number_format($r->tax ?? 0, 2) }} MXN</span></div>
                            <div class="flex justify-between border-t border-black/5 pt-1 text-base font-bold"><span>Total</span><span class="font-[Poppins] text-[#8C1D2B]">${{ number_format($r->total ?? 0, 2) }} MXN</span></div>
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Refund --}}
            @if ($r->isPaymentCompleted())
                <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Reembolsar</h3>
                    <p class="mt-1 text-sm text-[#2B1113]/60">Procesa un reembolso total o parcial con OpenPay. Se notificará al cliente por correo.</p>
                    <form method="POST" action="{{ route('admin.pagos.refund', $r) }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @csrf
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Monto (MXN)</span>
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $r->total }}" value="{{ $r->total }}" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Motivo</span>
                            <input type="text" name="reason" maxlength="255" placeholder="Ej. Cliente canceló el viaje" class="mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        </label>
                        <div class="sm:col-span-3">
                            <button type="submit" class="rounded-xl bg-[#991B1B] px-4 py-2 text-sm font-bold text-white hover:bg-[#7F1D1D]">Procesar reembolso</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($r->isPaymentRefunded())
                <div class="rounded-3xl bg-rose-50 p-6 ring-1 ring-rose-200">
                    <h3 class="font-[Poppins] text-base font-bold text-rose-700">Reembolso procesado</h3>
                    <dl class="mt-2 space-y-1 text-sm text-rose-900">
                        <div class="flex justify-between"><dt>Monto</dt><dd class="font-bold">${{ number_format($r->refund_amount ?? 0, 2) }} MXN</dd></div>
                        <div class="flex justify-between"><dt>Fecha</dt><dd>{{ $r->refunded_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                        @if ($r->refund_reason)
                            <div class="flex justify-between"><dt>Motivo</dt><dd>{{ $r->refund_reason }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>

        {{-- RIGHT: trip + customer --}}
        <div class="space-y-6">

            <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Viaje</h3>
                <p class="mt-2 font-[Poppins] text-lg font-extrabold text-[#2B1113]">{{ $trip->from ?? '—' }} → {{ $trip->to ?? '—' }}</p>
                <p class="text-xs text-[#2B1113]/60">{{ $trip->day?->format('d/m/Y') ?? '—' }} · {{ $trip->departure_time_formatted ?? '—' }}</p>
                <p class="mt-3 text-xs text-[#2B1113]/60">Asiento: <span class="font-bold text-[#2B1113]">{{ $r->seat?->label ?? '—' }}</span></p>
                <p class="text-xs text-[#2B1113]/60">Tipo: <span class="font-bold text-[#2B1113]">{{ $r->trip_type_label }}</span></p>
            </div>

            <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Cliente</h3>
                <p class="mt-2 text-sm font-bold text-[#2B1113]">{{ $r->customer_display_name }}</p>
                <p class="text-xs text-[#2B1113]/60">{{ $r->customer_display_email }}</p>
                @if ($r->customer_phone)
                    <p class="mt-1 text-xs text-[#2B1113]/60">Tel: {{ $r->customer_phone }}</p>
                @endif
                @if ($r->ip_address)
                    <p class="mt-3 text-[10px] text-[#2B1113]/40">IP: {{ $r->ip_address }}</p>
                @endif
            </div>

            @if ($r->openpay_barcode_url)
                <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Código de barras OXXO</h3>
                    <img src="{{ $r->openpay_barcode_url }}" alt="Código de barras" class="mt-3 h-32 w-full rounded-xl bg-white p-2 ring-1 ring-black/10 object-contain">
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
