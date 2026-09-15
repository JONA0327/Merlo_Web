<x-client-layout active="compras" title="Mis compras">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Mis compras</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Historial completo de tus compras de boletos.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @forelse ($purchases as $purchase)
        @php
            $root = $purchase->root;
            $trip = $root->landingRoute;
            $needsProof = $root->isTransfer() && $root->isPaymentPending() && ! $root->transfer_proof_path;
            $inValidation = $root->isTransfer() && $root->isPaymentPending() && $root->transfer_proof_path;
        @endphp
        <div class="mb-4 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="font-[Poppins] text-lg font-extrabold text-[#2B1113]">{{ $trip->from ?? '—' }}</p>
                        <svg class="h-4 w-4 shrink-0 text-[#8C1D2B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 10a.75.75 0 01.75-.75h11.19l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.25A.75.75 0 012.5 10z" clip-rule="evenodd"/></svg>
                        <p class="font-[Poppins] text-lg font-extrabold text-[#2B1113]">{{ $trip->to ?? '—' }}</p>
                    </div>
                    <p class="mt-1.5 text-xs text-[#2B1113]/60">
                        {{ $trip->day ? $trip->day->format('d/m/Y') : 'Sin fecha' }} · {{ $trip->departure_time_formatted ?? 'Sin horario' }} · {{ $root->trip_type_label }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        @foreach ($purchase->seats as $seat)
                            <span class="rounded-full bg-[#FFFBF6] px-2.5 py-1 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10">
                                Asiento {{ $seat->seat?->label ?? '—' }}
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-[#2B1113]/50">
                        Comprado el {{ $root->created_at->format('d/m/Y H:i') }} · {{ $root->payment_method_label }}
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="font-[Poppins] text-xl font-extrabold text-[#8C1D2B]">${{ number_format((float) $root->total, 2) }}</p>

                    @if ($root->isPaymentCompleted())
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-200">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            Pagado
                        </span>
                        <a href="{{ route('cliente.boletos') }}" class="mt-2 block text-xs font-bold text-[#8C1D2B] hover:underline">Ver boletos →</a>
                    @elseif ($needsProof)
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            Pendiente
                        </span>
                    @elseif ($inValidation)
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-3 py-1 text-[11px] font-bold text-sky-700 ring-1 ring-sky-200">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                            En validación
                        </span>
                    @elseif ($root->isPaymentFailed())
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-[11px] font-bold text-red-700 ring-1 ring-red-200">Fallido</span>
                    @elseif ($root->isPaymentRefunded())
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-[11px] font-bold text-rose-700 ring-1 ring-rose-200">Reembolsado</span>
                    @else
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200">Pendiente</span>
                    @endif
                </div>
            </div>

            @if ($needsProof)
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-bold text-amber-800">Falta tu comprobante de transferencia</p>
                    <p class="mt-1 text-xs text-amber-700">Es requisito subirlo para que podamos validar tu pago. Tu referencia es:</p>
                    <p class="mt-2 select-all break-all rounded-lg bg-white px-3 py-2 font-mono text-sm font-bold text-[#8C1D2B] ring-1 ring-amber-200">{{ $root->transfer_reference }}</p>

                    <form method="POST" action="{{ route('travel.payment.transfer.proof', $root) }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <input type="file" name="proof" accept="image/png,image/jpeg,application/pdf" required class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-xs text-[#2B1113] file:mr-3 file:rounded-lg file:border-0 file:bg-[#8C1D2B] file:px-3 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-[#6F1622]">
                        <button type="submit" class="shrink-0 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-xs font-bold text-white hover:bg-[#6F1622]">Subir comprobante</button>
                    </form>
                    @error('proof')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @elseif ($inValidation)
                <div class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 p-4">
                    <p class="text-sm font-bold text-sky-800">Comprobante recibido — en validación</p>
                    <p class="mt-1 text-xs text-sky-700">La validación tarda hasta 1 día. Te avisaremos por correo en cuanto se confirme tu pago.</p>
                    <form method="POST" action="{{ route('travel.payment.transfer.proof', $root) }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <input type="file" name="proof" accept="image/png,image/jpeg,application/pdf" required class="w-full rounded-xl border border-sky-200 bg-white px-3 py-2.5 text-xs text-[#2B1113] file:mr-3 file:rounded-lg file:border-0 file:bg-[#8C1D2B] file:px-3 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-[#6F1622]">
                        <button type="submit" class="shrink-0 rounded-xl bg-white px-5 py-2.5 text-xs font-bold text-[#2B1113] ring-1 ring-sky-200 hover:bg-sky-100">¿Comprobante equivocado? Reemplazar</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <x-empty-state
            title="Aún no tienes compras"
            description="Cuando completes una compra, aparecerá aquí con su recibo y detalles."
        >
            <x-slot name="icon">
                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.914a2 2 0 00-.586-1.414l-3.914-3.914A2 2 0 0011.086 2H4zm2 10a1 1 0 100 2h4a1 1 0 100-2H6zm0-4a1 1 0 100 2h4a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
            </x-slot>
        </x-empty-state>
    @endforelse
</x-client-layout>
