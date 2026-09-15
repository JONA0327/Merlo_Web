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

            @if ($r->isTransfer())
                <div class="overflow-hidden rounded-3xl bg-white ring-1 ring-black/5 shadow-sm">
                    <div class="bg-[#8C1D2B] px-6 py-4 text-white">
                        <p class="font-[Poppins] text-lg font-bold">Transferencia bancaria</p>
                        <p class="text-xs opacity-80">Total: ${{ number_format($r->total ?? 0, 2) }} MXN</p>
                    </div>
                    <div class="space-y-4 p-6">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Referencia esperada</p>
                            <p class="mt-1 select-all break-all font-mono text-xl font-extrabold text-[#8C1D2B]">{{ $r->transfer_reference ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Comprobante subido por el cliente</p>
                            @if ($r->transfer_proof_path)
                                <a href="{{ route('admin.pagos.transfer-proof', $r) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 rounded-xl bg-[#FFFBF6] px-4 py-2 text-xs font-bold text-[#8C1D2B] ring-1 ring-black/10 hover:bg-[#8C1D2B]/5">
                                    Ver comprobante
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                </a>
                            @else
                                <p class="mt-1 text-xs text-[#2B1113]/50">El cliente aún no ha subido un comprobante.</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Asientos apartados hasta</p>
                            <p class="mt-1 text-sm text-[#2B1113]">{{ $r->transfer_expires_at?->format('d/m/Y H:i') ?? '—' }}</p>
                        </div>

                        @if ($r->isPaymentPending())
                            <div class="border-t border-black/5 pt-4">
                                <p class="text-xs text-[#2B1113]/60">Copia el concepto que ves en tu cuenta bancaria real (o el que subió el cliente) y pégalo aquí para confirmar que coincide con esta reservación antes de marcarla como pagada.</p>
                                <form method="POST" action="{{ route('admin.pagos.validate-transfer', $r) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    @csrf
                                    <input type="text" name="reference_confirm" placeholder="Pega aquí el concepto" class="w-full rounded-xl border border-black/10 bg-white px-3 py-2 font-mono text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    <button type="submit" class="shrink-0 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">Marcar como pagada</button>
                                </form>
                                <form method="POST" action="{{ route('admin.pagos.reject-transfer', $r) }}" class="mt-2" onsubmit="return confirm('¿Rechazar esta transferencia? Los asientos volverán a estar disponibles.')">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700">Rechazar y liberar asientos</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @else
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
            @endif

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

            {{-- Reventa del regreso --}}
            @if ($r->isRoundTrip() && $r->isPaymentCompleted() && ! $r->isReturnVerified())
                <div class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                    <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Regreso</h3>
                    @if ($r->isReturnResold())
                        <p class="mt-2 text-sm text-emerald-700">Este regreso ya fue revendido — <a href="{{ route('admin.pagos.show', $r->resold_return_reservation_id) }}" class="font-bold hover:underline">ver boleto nuevo</a>.</p>
                    @elseif ($r->isResaleWindowOpen())
                        <p class="mt-1 text-sm text-[#2B1113]/60">Liberado {{ $r->return_released_at->diffForHumans() }}. Disponible para reventa hasta {{ $r->return_resale_expires_at->format('d/m/Y H:i') }}.</p>
                    @else
                        <p class="mt-1 text-sm text-[#2B1113]/60">Si el pasajero avisó que no usará el tramo de regreso, puedes liberar ese asiento para venderlo a otro cliente por tiempo limitado.</p>
                        <form method="POST" action="{{ route('admin.pagos.release-return', $r) }}" class="mt-3" onsubmit="return confirm('¿Liberar el regreso de este boleto para reventa?')">
                            @csrf
                            <button type="submit" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-sm font-bold text-white hover:bg-[#6F1622]">Liberar regreso para reventa</button>
                        </form>
                    @endif
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
                @if ($groupSeats->isEmpty())
                    <p class="mt-3 text-xs text-[#2B1113]/60">Asiento: <span class="font-bold text-[#2B1113]">{{ $r->seat?->label ?? '—' }}</span></p>
                @else
                    <p class="mt-3 text-xs text-[#2B1113]/60">Asientos ({{ $groupSeats->count() + 1 }}):</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $r->seat?->label ?? '—' }}</span>
                        @foreach ($groupSeats as $groupSeat)
                            <span class="rounded-md bg-[#FFFBF6] px-2 py-0.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">{{ $groupSeat->seat?->label ?? '—' }}</span>
                        @endforeach
                    </div>
                @endif
                <p class="mt-2 text-xs text-[#2B1113]/60">Tipo: <span class="font-bold text-[#2B1113]">{{ $r->trip_type_label }}</span></p>
                @if ($r->isReturnLeg())
                    <p class="mt-2 text-xs text-[#2B1113]/60">Regreso revendido, originado del pago <a href="{{ route('admin.pagos.show', $r->source_reservation_id) }}" class="font-bold text-[#8C1D2B] hover:underline">#{{ $r->source_reservation_id }}</a>.</p>
                @endif
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
