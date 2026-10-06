from pathlib import Path

path = Path(r"D:\Proyectos y Aplicaiones\Proyectos\Proyectos MERLO\Merlo_Web_Tickets\resources\views\admin\asientos\show.blade.php")
text = path.read_text(encoding="utf-8")

# Replace the @if guard for the WhatsApp button. Previously the button
# only appeared for pending reservations — now we want to keep it
# visible for any apartado that hasn't been *delivered* yet, so the
# admin can manually re-send or send a freshly-marked-paid apartado.
old = """                                @if ($reservation->isPending() && ! $trip->hasEnded())
                                    <form method="POST" action="{{ route('admin.asientos.send', [$trip, $reservation]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 transition-colors">
                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                            Enviar boleto{{ $allSeats->count() > 1 ? 's' : '' }}
                                        </button>"""

new = """                                @if (! $reservation->ticket_sent_at && ! $trip->hasEnded())
                                    <form method="POST" action="{{ route('admin.asientos.send', [$trip, $reservation]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 transition-colors">
                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                            Enviar boleto{{ $allSeats->count() > 1 ? 's' : '' }}
                                        </button>"""

if old in text:
    text = text.replace(old, new)
    print("Send-button condition relaxed.")
else:
    print("Send button NOT FOUND")

path.write_text(text, encoding="utf-8")