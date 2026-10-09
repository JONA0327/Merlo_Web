<x-admin-layout active="whatsapp" title="WhatsApp">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">WhatsApp</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Conecta un número de WhatsApp (vía Evolution API) para que "Enviar boleto" en Apartar asientos también mande el boleto por WhatsApp, además del correo.</p>
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_1fr]">
        {{-- ===================== Datos de conexión ===================== --}}
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Datos de conexión</h3>
            <p class="mt-1 text-xs text-[#2B1113]/50">De tu servidor de Evolution API (self-hosted). El nombre de instancia puede ser uno que ya exista o uno nuevo — si no existe, se crea solo al generar el código QR.</p>

            <form method="POST" action="{{ route('admin.whatsapp.update') }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="evolution_api_url" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">URL del servidor</label>
                    <input id="evolution_api_url" name="evolution_api_url" type="text" value="{{ old('evolution_api_url', $setting->evolution_api_url) }}" placeholder="https://mi-evolution-api.com" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('evolution_api_url')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="evolution_api_key" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Apikey</label>
                    <input id="evolution_api_key" name="evolution_api_key" type="password" value="{{ old('evolution_api_key', $setting->evolution_api_key) }}" placeholder="Apikey global o de la instancia" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 font-mono text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('evolution_api_key')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="evolution_instance" class="mb-1.5 block text-sm font-semibold text-[#2B1113]">Nombre de instancia</label>
                    <input id="evolution_instance" name="evolution_instance" type="text" value="{{ old('evolution_instance', $setting->evolution_instance) }}" placeholder="merlo" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 font-mono text-sm text-[#2B1113] placeholder:text-[#2B1113]/40 focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    @error('evolution_instance')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#8C1D2B] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                    Guardar
                </button>
            </form>
        </section>

        {{-- ===================== Conexión / QR ===================== --}}
        <section class="rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Conectar teléfono</h3>
                @if ($setting->evolutionConfigured())
                    <span id="wa-status-badge" class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-600">Verificando…</span>
                @endif
            </div>

            @if (! $setting->evolutionConfigured())
                <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-[#FFFBF6] px-4 py-8 text-center text-sm text-[#2B1113]/60">
                    Guarda la URL, la apikey y el nombre de instancia primero — en cuanto los guardes, aquí aparece el botón para generar el código QR.
                </div>
            @else
                <p class="mt-2 text-xs text-[#2B1113]/50">Abre WhatsApp en el teléfono que vas a usar → <strong>Dispositivos vinculados</strong> → <strong>Vincular un dispositivo</strong> → escanea el código.</p>

                <div id="wa-error" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"></div>

                <div id="wa-qr-wrap" class="mt-4 hidden flex-col items-center gap-2 rounded-2xl border border-dashed border-[#8C1D2B]/30 bg-[#FFFBF6] p-6">
                    <img id="wa-qr-image" src="" alt="Código QR de WhatsApp" class="h-56 w-56 rounded-lg bg-white p-2 ring-1 ring-black/10">
                    <p class="text-[11px] text-[#2B1113]/50">El código se refresca solo si expira. Esta pantalla se actualiza en cuanto termines de escanear.</p>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" id="wa-generate-qr" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                        Generar código QR
                    </button>

                    <form method="POST" action="{{ route('admin.whatsapp.disconnect') }}" data-confirm="¿Desconectar este número de WhatsApp?">
                        @csrf
                        <button type="submit" class="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-[#2B1113]/60 ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                            Desconectar
                        </button>
                    </form>
                </div>
            @endif
        </section>
    </div>

    @if ($setting->evolutionConfigured())
        <script>
            (function () {
                const generateBtn = document.getElementById('wa-generate-qr');
                const qrImg = document.getElementById('wa-qr-image');
                const qrWrap = document.getElementById('wa-qr-wrap');
                const statusBadge = document.getElementById('wa-status-badge');
                const errorEl = document.getElementById('wa-error');
                if (!generateBtn) return;

                const statusMeta = {
                    open: { text: 'Conectado', cls: 'bg-emerald-100 text-emerald-700' },
                    connecting: { text: 'Conectando…', cls: 'bg-amber-100 text-amber-700' },
                    close: { text: 'Desconectado', cls: 'bg-slate-200 text-slate-600' },
                    unknown: { text: 'Desconocido', cls: 'bg-slate-200 text-slate-600' },
                };

                let pollTimer = null;

                function stopPolling() {
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }
                }

                function setStatus(state) {
                    const meta = statusMeta[state] ?? statusMeta.unknown;
                    statusBadge.textContent = meta.text;
                    statusBadge.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ' + meta.cls;
                    if (state === 'open') {
                        qrWrap.classList.add('hidden');
                        qrWrap.classList.remove('flex');
                        stopPolling();
                    }
                }

                async function checkStatus() {
                    try {
                        const res = await fetch('{{ route('admin.whatsapp.status') }}', { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        setStatus(data.state);
                    } catch (e) {
                        // Transient network hiccup — the next poll tries again.
                    }
                }

                async function generateQr() {
                    errorEl.classList.add('hidden');
                    generateBtn.disabled = true;
                    generateBtn.textContent = 'Generando…';
                    try {
                        const res = await fetch('{{ route('admin.whatsapp.qr') }}', { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.error || 'No se pudo generar el código QR.');
                        }
                        if (data.connected) {
                            setStatus('open');
                        } else if (data.qr) {
                            qrImg.src = data.qr;
                            qrWrap.classList.remove('hidden');
                            qrWrap.classList.add('flex');
                            stopPolling();
                            pollTimer = setInterval(checkStatus, 4000);
                        } else {
                            throw new Error('Evolution API no devolvió un código QR. Intenta de nuevo en unos segundos.');
                        }
                    } catch (e) {
                        errorEl.textContent = e.message;
                        errorEl.classList.remove('hidden');
                    } finally {
                        generateBtn.disabled = false;
                        generateBtn.textContent = 'Generar código QR';
                    }
                }

                generateBtn.addEventListener('click', generateQr);
                checkStatus();
            })();
        </script>
    @endif
</x-admin-layout>
