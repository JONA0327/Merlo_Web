<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Editar {{ $busUnit->name }} · Merlo Transportes</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans text-[#2B1113] antialiased">
        <div class="flex min-h-full flex-col bg-[#FFFBF6]">
            {{-- ===================== TOPBAR ===================== --}}
            <header class="flex h-16 shrink-0 items-center justify-between border-b border-black/10 bg-white px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="shrink-0">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-8 w-auto">
                    </a>
                    <div class="min-w-0 border-l border-black/10 pl-3">
                        <h1 class="truncate font-[Poppins] text-base font-bold text-[#2B1113]">{{ $busUnit->name }}</h1>
                        <p class="truncate text-xs text-[#2B1113]/50">Arrastra los asientos para posicionarlos en el mapa de la unidad.</p>
                    </div>
                </div>
                <a href="{{ route('admin.unidades') }}" class="shrink-0 text-sm font-semibold text-blue-600 hover:text-blue-700">
                    Volver a unidades
                </a>
            </header>

            @if (session('success'))
                <div class="shrink-0 border-b border-emerald-200 bg-emerald-50 px-6 py-2 text-sm font-medium text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ===================== 1. DATOS DE LA UNIDAD (barra horizontal) ===================== --}}
            <details id="unit-data-bar" class="group shrink-0 border-b border-black/10 bg-white">
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 sm:px-6">
                    <span class="font-[Poppins] text-sm font-bold text-[#2B1113]">1. Datos de la unidad</span>
                    <svg class="h-4 w-4 shrink-0 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </summary>

                <form method="POST" action="{{ route('admin.unidades.update', $busUnit) }}" class="flex flex-wrap items-end gap-4 border-t border-black/5 px-4 py-4 sm:px-6" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="w-full sm:w-48">
                        <label for="name" class="mb-1.5 block text-xs font-semibold text-[#2B1113]">Nombre</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $busUnit->name) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                        @error('name')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="w-full sm:w-56">
                        <label for="description" class="mb-1.5 block text-xs font-semibold text-[#2B1113]">Descripción</label>
                        <input id="description" name="description" type="text" value="{{ old('description', $busUnit->description) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                    </div>

                    <div class="w-24">
                        <label for="canvas_width" class="mb-1.5 block text-xs font-semibold text-[#2B1113]">Ancho</label>
                        <input id="canvas_width" name="canvas_width" type="number" min="200" value="{{ old('canvas_width', $busUnit->canvas_width) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    </div>

                    <div class="w-24">
                        <label for="canvas_height" class="mb-1.5 block text-xs font-semibold text-[#2B1113]">Alto</label>
                        <input id="canvas_height" name="canvas_height" type="number" min="200" value="{{ old('canvas_height', $busUnit->canvas_height) }}" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                    </div>

                    <div class="w-full sm:w-64">
                        <label for="background_image" class="mb-1.5 block text-xs font-semibold text-[#2B1113]">Imagen de fondo (plano)</label>
                        @if ($busUnit->background_image)
                            <div class="mb-1.5 flex items-center gap-2">
                                <img src="{{ $busUnit->background_image_url }}" alt="Plano actual" class="h-10 w-14 rounded-lg object-cover ring-1 ring-black/10">
                                <label class="flex items-center gap-1.5 text-xs font-semibold text-red-600">
                                    <input type="checkbox" name="remove_background_image" value="1" class="h-3.5 w-3.5 rounded border-black/20 text-red-600 focus:ring-red-500">
                                    Quitar
                                </label>
                            </div>
                        @endif
                        <input id="background_image" name="background_image" type="file" accept="image/*" class="w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-3 py-2.5 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                        @error('background_image')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-2.5 text-sm font-semibold text-[#2B1113]">
                        <input type="checkbox" name="has_upper_deck" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $busUnit->has_upper_deck ? 'checked' : '' }}>
                        Doble piso
                    </label>

                    <label class="flex items-center gap-2 rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-2.5 text-sm font-semibold text-[#2B1113]">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $busUnit->is_active ? 'checked' : '' }}>
                        Activa
                    </label>

                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                        Guardar datos
                    </button>
                </form>
            </details>

            {{-- ===================== 2. HERRAMIENTAS (barra horizontal) ===================== --}}
            <details id="tools-bar" class="group shrink-0 border-b border-black/10 bg-[#FFFBF6]" open>
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 sm:px-6">
                    <span class="font-[Poppins] text-sm font-bold text-[#2B1113]">2. Herramientas</span>
                    <svg class="h-4 w-4 shrink-0 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </summary>

                <div class="flex flex-wrap items-start gap-6 border-t border-black/5 px-4 py-4 sm:px-6">
                    {{-- Vista --}}
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Vista</p>
                        <div class="flex items-center gap-2">
                            <button type="button" id="view-zoom-out" class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-lg font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">−</button>
                            <span id="view-zoom-label" class="w-12 text-center text-xs font-bold text-[#2B1113]/70">100%</span>
                            <button type="button" id="view-zoom-in" class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-lg font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">+</button>
                            <button type="button" id="view-zoom-reset" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">Restablecer</button>
                            <button type="button" id="view-pan-toggle" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">Modo: Seleccionar</button>
                            <button type="button" id="select-all" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5" title="Ctrl+A">Seleccionar todo</button>
                            <button type="button" id="selection-delete" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40" title="Supr/Backspace" disabled>Eliminar selección</button>
                        </div>
                    </div>

                    @if ($busUnit->has_upper_deck)
                        <div class="space-y-2 border-l border-black/10 pl-6">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Piso</p>
                            <div class="flex rounded-xl bg-white p-1 ring-1 ring-black/10">
                                <button type="button" id="deck-lower" class="deck-tab rounded-lg px-3 py-2 text-xs font-bold transition-colors">Planta baja</button>
                                <button type="button" id="deck-upper" class="deck-tab rounded-lg px-3 py-2 text-xs font-bold transition-colors">Planta alta</button>
                            </div>
                        </div>
                    @endif

                    {{-- Generar grilla --}}
                    <div class="space-y-2 border-l border-black/10 pl-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Generar grilla</p>
                        <div class="flex items-end gap-2">
                            <label class="flex flex-col gap-1 text-xs font-semibold text-[#2B1113]">
                                Filas
                                <input type="number" id="grid-rows" min="1" max="20" value="4" class="w-16 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                            </label>
                            <label class="flex flex-col gap-1 text-xs font-semibold text-[#2B1113]">
                                Columnas
                                <input type="number" id="grid-cols" min="1" max="10" value="4" class="w-16 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                            </label>
                            <label class="flex flex-col gap-1 text-xs font-semibold text-[#2B1113]">
                                Prefijo
                                <input type="text" id="grid-prefix" maxlength="4" placeholder="Ej. V" class="w-16 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                            </label>
                            <button type="button" id="grid-generate" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2B1113] px-4 py-2.5 text-sm font-bold text-white hover:bg-black/80 transition-colors">
                                Generar
                            </button>
                        </div>
                    </div>

                    {{-- Agregar --}}
                    <div class="space-y-2 border-l border-black/10 pl-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Agregar</p>
                        <div class="flex items-center gap-2">
                            <button type="button" id="seat-add" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#8C1D2B] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#6F1622] transition-colors">
                                + Asiento
                            </button>
                            <select id="object-kind" class="rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                <option value="door">Puerta</option>
                                <option value="stairs">Escaleras</option>
                                <option value="driver">Chofer</option>
                                <option value="bathroom">Baño</option>
                                <option value="table">Mesa</option>
                                <option value="other">Otro</option>
                                <option value="divider">Separador (sin nombre)</option>
                                <option value="outline">Contorno (silueta del bus)</option>
                            </select>
                            <button type="button" id="object-add" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 transition-colors">
                                + Objeto
                            </button>
                        </div>
                    </div>

                    {{-- Color del contorno seleccionado --}}
                    <div class="space-y-2 border-l border-black/10 pl-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">Color contorno</p>
                        <input type="color" id="outline-color" value="#2B1113" class="h-9 w-16 cursor-pointer rounded-lg border border-black/10 bg-white p-1 disabled:cursor-not-allowed disabled:opacity-40" disabled title="Selecciona un contorno para cambiar su color">
                    </div>

                    {{-- Guardar --}}
                    <div class="ml-auto space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#2B1113]/40 opacity-0">Guardar</p>
                        <button type="button" id="seat-save" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#F5B301] px-6 py-2.5 text-sm font-bold text-[#2B1113] hover:bg-[#E0A400] transition-colors">
                            Guardar distribución
                        </button>
                    </div>

                    <p id="seat-status" class="w-full text-sm font-medium"></p>
                </div>
            </details>

            {{-- ===================== 3. LIENZO ===================== --}}
            <div class="flex flex-1 flex-col p-4">
                <p class="mb-2 shrink-0 text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">3. Mapa de asientos</p>
                <div id="seat-canvas-wrap" class="relative min-h-[650px] flex-1">
                    <div id="seat-canvas" class="h-full w-full overflow-auto rounded-2xl border border-black/10 bg-white"></div>
                </div>
                <p class="mt-2 shrink-0 text-xs text-[#2B1113]/50">Clic para seleccionar un asiento, arrastra sobre el fondo para seleccionar varios (Shift+clic para sumar/quitar de la selección). Arrastra cualquier asiento seleccionado para mover todo el grupo junto, a cualquier nivel de zoom. Con un solo elemento seleccionado aparecen manijas para ajustar sus lados y esquinas. Doble clic sobre un asiento u objeto para cambiar su número/folio. Usa el botón "Eliminar selección" o la tecla Supr/Backspace para borrar lo seleccionado. Para mover la vista del lienzo: mantén presionada la barra espaciadora y arrastra, arrastra con el botón central del mouse, o activa "Modo: Mover vista" — funciona desde cualquier punto, incluso sobre un asiento. El objeto "Contorno" agrega un rectángulo grande para dibujar la silueta del bus detrás de los asientos — arrástralo o ajusta sus lados con las manijas, y cambia su color con el selector "Color contorno".</p>
            </div>
        </div>

        <script>
            window.__SEAT_EDITOR__ = {
                unitName: {!! json_encode($busUnit->name) !!},
                canvasWidth: {{ $busUnit->canvas_width }},
                canvasHeight: {{ $busUnit->canvas_height }},
                backgroundImageUrl: {!! json_encode($busUnit->background_image_url) !!},
                hasUpperDeck: {{ $busUnit->has_upper_deck ? 'true' : 'false' }},
                syncUrl: {!! json_encode(route('admin.unidades.seats.sync', $busUnit)) !!},
                seats: {!! json_encode($busUnit->seats->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'kind' => $s->kind,
                    'type' => $s->type,
                    'deck' => $s->deck,
                    'shape' => $s->shape,
                    'width' => $s->width,
                    'height' => $s->height,
                    'corner_radius' => $s->corner_radius,
                    'border_width' => $s->border_width,
                    'color' => $s->color,
                    'pos_x' => $s->pos_x,
                    'pos_y' => $s->pos_y,
                ])) !!},
            };
        </script>
        @vite(['resources/js/seat-editor.js'])
    </body>
</html>
