from pathlib import Path

path = Path(r"D:\Proyectos y Aplicaiones\Proyectos\Proyectos MERLO\Merlo_Web_Tickets\resources\views\admin\asientos\show.blade.php")
text = path.read_text(encoding="utf-8")

# Replace the small one-row edit form (trip_type only) with a
# three-field panel: category, payment method, payment status.
old_edit_form = """                        @if (! $reservation->isFullyCheckedIn() && ! $trip->hasEnded())
                            <div id="edit-category-{{ $reservation->id }}" class="admin-edit-category-panel mt-3 hidden rounded-xl bg-white p-3 ring-1 ring-black/10">
                                <form method="POST" action="{{ route('admin.asientos.update-category', [$trip, $reservation]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="trip_type" class="flex-1 rounded-lg border border-black/10 bg-[#FFFBF6] px-2 py-1.5 text-xs font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                        @foreach (\App\Models\TripTicketPrice::tripTypes() as $type => $label)
                                            <option value="{{ $type }}" {{ $reservation->trip_type === $type ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="shrink-0 rounded-lg bg-[#8C1D2B] px-3 py-1.5 text-[11px] font-bold text-white hover:bg-[#6F1622] transition-colors">Guardar</button>
                                </form>
                            </div>
                        @endif"""

new_edit_form = """                        @if (! $reservation->isFullyCheckedIn() && ! $trip->hasEnded())
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
                                                <option value="" {{ ! in_array($reservation->payment_method, ['transfer','cash']) ? 'selected' : '' }}>(sin método)</option>
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
                        @endif"""

if old_edit_form in text:
    text = text.replace(old_edit_form, new_edit_form)
    print("Expanded edit form to include payment fields.")
else:
    print("Edit form block NOT FOUND")

path.write_text(text, encoding="utf-8")