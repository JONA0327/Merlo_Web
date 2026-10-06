from pathlib import Path
import re

path = Path(r"D:\Proyectos y Aplicaiones\Proyectos\Proyectos MERLO\Merlo_Web_Tickets\resources\views\payment\pending.blade.php")
text = path.read_text(encoding="utf-8")

old_block = """        @if ($isCashAtWindow)
            <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#F5B301] text-base font-extrabold text-[#2B1113]">$</div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">Pago en ventanilla</h2>
                        <p class="mt-1 text-sm text-[#2B1113]/70">
                            Tu asiento está apartado sin fecha de vencimiento. Cuando pagues en efectivo en ventanilla, un operador confirmará el pago y te entregaremos tu boleto con código QR al momento.
                        </p>
                    </div>
                </div>

                <dl class="mt-5 space-y-2 border-t border-black/5 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">A pagar</dt><dd class="font-bold text-[#2B1113]">${{ number_format($reservation->total, 2) }} MXN</dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Estado</dt><dd><span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800"><span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>RESERVADO</span></dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Vencimiento</dt><dd>Sin vencimiento — pagado en ventanilla o cancelado por un operador</dd></div>
                </dl>

                <p class="mt-5 text-[11px] text-[#2B1113]/50">
                    Conserva este comprobante. Sin él, un operador no podrá identificar tu apartado en ventanilla.
                </p>
            </div>
        @endif"""

new_block = """        @if ($isCashAtWindow)
            @php
                // $reservation->total is just the root row's total — for a
                // multi-seat cash purchase we have to sum the whole group,
                // otherwise the "A pagar" line and the per-row breakdown
                // disagree (and the operator at the window would chase the
                // missing cash).
                $groupTotal = (float) $group->sum(fn ($r) => (float) $r->total);
                $seatCount = max(1, $group->count());
            @endphp
            <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#F5B301] text-base font-extrabold text-[#2B1113]">$</div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-[Poppins] text-base font-bold text-[#2B1113]">Pago en ventanilla</h2>
                        <p class="mt-1 text-sm text-[#2B1113]/70">
                            @if ($seatCount > 1)
                                Tus {{ $seatCount }} asientos están apartados sin fecha de vencimiento. Cuando pagues en efectivo en ventanilla, un operador confirmará el pago y te entregaremos tus boletos con código QR al momento.
                            @else
                                Tu asiento está apartado sin fecha de vencimiento. Cuando pagues en efectivo en ventanilla, un operador confirmará el pago y te entregaremos tu boleto con código QR al momento.
                            @endif
                        </p>
                    </div>
                </div>

                <dl class="mt-5 space-y-2 border-t border-black/5 pt-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-[#2B1113]/60">A pagar{{ $seatCount > 1 ? ' ('.$seatCount.' asientos)' : '' }}</dt>
                        <dd class="font-bold text-[#2B1113]">${{ number_format($groupTotal, 2) }} MXN</dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Estado</dt><dd><span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800"><span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>RESERVADO</span></dd></div>
                    <div class="flex justify-between"><dt class="text-[#2B1113]/60">Vencimiento</dt><dd>Sin vencimiento — pagado en ventanilla o cancelado por un operador</dd></div>
                </dl>

                <p class="mt-5 text-[11px] text-[#2B1113]/50">
                    Conserva este comprobante. Sin él, un operador no podrá identificar tu apartado en ventanilla.
                </p>
            </div>
        @endif"""

if old_block in text:
    text = text.replace(old_block, new_block)
    print("Replaced.")
else:
    print("NOT FOUND.")

path.write_text(text, encoding="utf-8")