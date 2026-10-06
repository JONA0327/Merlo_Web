from pathlib import Path

path = Path(r"D:\Proyectos y Aplicaiones\Proyectos\Proyectos MERLO\Merlo_Web_Tickets\resources\views\admin\asientos\show.blade.php")
text = path.read_text(encoding="utf-8")

# 1. Remove the "Tarjeta" / card option from the create form's payment_method select
old_select = """                            <select name="payment_method" id="admin-payment-method-select" required class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                                <option value="card" {{ old('payment_method', 'card') === 'card' ? 'selected' : '' }}>Tarjeta</option>
                                <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                            </select>"""

new_select = """                            <select name="payment_method" id="admin-payment-method-select" required class="mt-1 w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm font-bold text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                                <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                            </select>"""

if old_select in text:
    text = text.replace(old_select, new_select)
    print("Removed 'Tarjeta/card' option from create form.")
else:
    print("Create form: select block NOT FOUND")

path.write_text(text, encoding="utf-8")