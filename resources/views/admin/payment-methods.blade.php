<x-admin-layout active="payment-methods" title="Métodos de pago">
    <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Métodos de pago</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Cuentas para recibir transferencias — se muestran al cliente al momento de pagar su boleto.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_1.6fr]">
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Agregar cuenta</h3>

            <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="label" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Etiqueta</label>
                    <input id="label" name="label" type="text" value="{{ old('label') }}" placeholder="Cuenta principal" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('label')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bank_name" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Banco</label>
                    <input id="bank_name" name="bank_name" type="text" value="{{ old('bank_name') }}" placeholder="BBVA" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @error('bank_name')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="beneficiary_name" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Nombre del beneficiario</label>
                    <input id="beneficiary_name" name="beneficiary_name" type="text" value="{{ old('beneficiary_name') }}" placeholder="Merlo Transportes SA de CV" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('beneficiary_name')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="clabe" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">CLABE interbancaria</label>
                    <input id="clabe" name="clabe" type="text" inputmode="numeric" maxlength="18" value="{{ old('clabe') }}" placeholder="18 dígitos" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 font-mono text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @error('clabe')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="card_number" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Número de tarjeta (opcional)</label>
                    <input id="card_number" name="card_number" type="text" inputmode="numeric" maxlength="19" value="{{ old('card_number') }}" placeholder="16-19 dígitos" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 font-mono text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    @error('card_number')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Orden</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    </div>
                    <div class="flex items-end">
                        <label class="flex w-full items-center justify-between rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm font-semibold text-[#2B1113]">
                            <span>Activa</span>
                            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" checked>
                        </label>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                    Guardar cuenta
                </button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Cuentas configuradas</h3>

            @if ($paymentMethods->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                    Aún no hay cuentas configuradas. Mientras no haya ninguna activa, el checkout no podrá mostrar datos para transferir.
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($paymentMethods as $method)
                        <details class="group rounded-2xl border border-black/5 bg-[#FFFBF6] p-4">
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                    <p class="font-[Poppins] text-base font-bold text-[#2B1113]">{{ $method->label }}</p>
                                    <p class="mt-1 text-sm text-[#2B1113]/60">{{ $method->bank_name ?? 'Sin banco especificado' }} · {{ $method->beneficiary_name }}</p>
                                    @if ($method->clabe)
                                        <p class="mt-0.5 font-mono text-xs text-[#2B1113]/50">CLABE {{ $method->clabe }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $method->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $method->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </summary>

                            <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="mt-4 space-y-3 border-t border-black/5 pt-4">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#2B1113]/60">Etiqueta</label>
                                        <input name="label" type="text" value="{{ $method->label }}" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#2B1113]/60">Banco</label>
                                        <input name="bank_name" type="text" value="{{ $method->bank_name }}" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-[#2B1113]/60">Nombre del beneficiario</label>
                                        <input name="beneficiary_name" type="text" value="{{ $method->beneficiary_name }}" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#2B1113]/60">CLABE</label>
                                        <input name="clabe" type="text" inputmode="numeric" maxlength="18" value="{{ $method->clabe }}" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 font-mono text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#2B1113]/60">Tarjeta</label>
                                        <input name="card_number" type="text" inputmode="numeric" maxlength="19" value="{{ $method->card_number }}" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 font-mono text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#2B1113]/60">Orden</label>
                                        <input name="sort_order" type="number" min="0" value="{{ $method->sort_order }}" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex w-full items-center justify-between rounded-lg border border-black/10 bg-white px-3 py-2 text-sm font-semibold text-[#2B1113]">
                                            <span>Activa</span>
                                            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $method->is_active ? 'checked' : '' }}>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="submit" class="rounded-lg bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6F1622]">Guardar cambios</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700" onclick="return confirm('¿Eliminar esta cuenta?')">
                                    Eliminar
                                </button>
                            </form>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-admin-layout>
