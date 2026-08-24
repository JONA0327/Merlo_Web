{{-- Trip quote modal (opens from the "Cotizar" banner button) --}}
<div id="auth-modal-cotizar" class="js-modal fixed inset-0 z-[100] hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
        <button type="button" class="js-modal-close absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full text-[#2B1113]/40 hover:bg-black/5 hover:text-[#2B1113]" aria-label="Cerrar">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
        </button>

        <div class="text-center mb-2">
            <span class="text-xs font-bold uppercase tracking-widest text-[#8C1D2B]">¿Viaje especial o en grupo?</span>
            <h2 class="mt-2 font-[Poppins] text-xl font-bold text-[#2B1113]">Cotiza tu viaje por WhatsApp</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Cuéntanos los detalles y te respondemos directo por WhatsApp.</p>
        </div>

        <form
            x-data="{
                name: '', phone: '', from: '', to: '', departureDate: '', returnDate: '', message: '',
                send() {
                    const lines = [
                        'Hola, quiero cotizar un viaje:',
                        `Nombre: ${this.name}`,
                        this.phone ? `Teléfono: ${this.phone}` : null,
                        `Origen: ${this.from}`,
                        `Destino: ${this.to}`,
                        `Fecha de salida: ${this.departureDate}`,
                        this.returnDate ? `Fecha de regreso: ${this.returnDate}` : null,
                        this.message ? `Notas: ${this.message}` : null,
                    ].filter(Boolean);
                    const url = 'https://wa.me/{{ $whatsappDigits }}?text=' + encodeURIComponent(lines.join('\n'));
                    window.open(url, '_blank');
                },
            }"
            @submit.prevent="send()"
            class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4"
        >
            <div>
                <x-input-label for="quote_name" value="Nombre" />
                <x-text-input id="quote_name" type="text" x-model="name" required class="block mt-1.5 w-full" />
            </div>
            <div>
                <x-input-label for="quote_phone" value="Teléfono (opcional)" />
                <x-text-input id="quote_phone" type="tel" x-model="phone" class="block mt-1.5 w-full" />
            </div>
            <div>
                <x-input-label for="quote_from" value="Origen" />
                <x-text-input id="quote_from" type="text" x-model="from" required class="block mt-1.5 w-full" />
            </div>
            <div>
                <x-input-label for="quote_to" value="Destino" />
                <x-text-input id="quote_to" type="text" x-model="to" required class="block mt-1.5 w-full" />
            </div>
            <div>
                <x-input-label for="quote_departure_date" value="Fecha de salida" />
                <x-text-input id="quote_departure_date" type="date" x-model="departureDate" required class="block mt-1.5 w-full" />
            </div>
            <div>
                <x-input-label for="quote_return_date" value="Fecha de regreso (opcional)" />
                <x-text-input id="quote_return_date" type="date" x-model="returnDate" class="block mt-1.5 w-full" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="quote_message" value="Notas adicionales (opcional)" />
                <textarea id="quote_message" x-model="message" rows="3" class="mt-1.5 block w-full rounded-xl border-black/10 focus:border-[#8C1D2B] focus:ring-[#8C1D2B]"></textarea>
            </div>

            @if ($whatsappDigits)
                <div class="sm:col-span-2 flex justify-center pt-2">
                    <button type="submit" class="inline-flex items-center justify-center gap-2.5 rounded-full bg-[#25D366] px-8 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#25D366]/25 hover:bg-[#1DA851] transition-colors">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.001 2C5.583 2 2 5.583 2 10.001c0 1.517.424 2.982 1.226 4.253L2 18l3.851-1.207A7.958 7.958 0 0010 18c4.418 0 8-3.582 8-7.999C18 5.583 14.419 2 10.001 2zm0 14.4a6.36 6.36 0 01-3.397-.978l-.243-.148-2.53.793.8-2.469-.163-.253A6.358 6.358 0 013.6 10c0-3.529 2.87-6.4 6.401-6.4 3.529 0 6.399 2.871 6.399 6.4 0 3.53-2.87 6.4-6.399 6.4z"/></svg>
                        Enviar cotización por WhatsApp
                    </button>
                </div>
            @else
                <div class="sm:col-span-2 pt-2 text-center text-sm text-[#2B1113]/50">
                    El envío por WhatsApp aún no está disponible. Escríbenos a <a href="mailto:atencion@merlotransportes.com" class="font-semibold text-[#8C1D2B] hover:underline">atencion@merlotransportes.com</a>.
                </div>
            @endif
        </form>
    </div>
</div>
