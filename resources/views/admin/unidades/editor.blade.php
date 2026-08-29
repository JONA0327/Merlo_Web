<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Editar {{ $busUnit->name }} · Merlo Transportes</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            /* Sidebar collapse animation — width transitions in/out, and the
             * labels/chevrons/content fade out so only the section icons
             * remain visible (matches the "thin vertical strip" reference
             * drawing). Keeping the icons visible (rather than hiding the
             * whole summary) means the user still sees what tools exist
             * even when the panel is collapsed, without losing canvas space
             * to a long list of empty space. */
            #tools-sidebar {
                width: 16rem; /* 256px — fits a small form comfortably */
                transition: width 200ms ease-out;
            }
            #tools-sidebar[data-collapsed="true"] {
                width: 3rem; /* 48px — just enough for the section icons */
            }
            #tools-sidebar[data-collapsed="true"] .sidebar-hide-when-collapsed {
                display: none;
            }
            #tools-sidebar[data-collapsed="true"] details > div {
                display: none;
            }
            #tools-sidebar[data-collapsed="true"] details > summary {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            #tools-sidebar[data-collapsed="true"] details > summary > svg.section-icon {
                margin: 0;
            }
            /* Rotate the toggle chevron so it always points in the
             * direction the sidebar would move to if you clicked it:
             * left while expanded (collapse to a thin strip), right while
             * collapsed (expand back out). */
            #sidebar-toggle-icon {
                transform: rotate(0deg);
                transition: transform 200ms ease-out;
            }
            #tools-sidebar[data-collapsed="true"] #sidebar-toggle-icon {
                transform: rotate(180deg);
            }
            /* Per-seat editing section in the editor: only shows when the
             * JS flips data-has-selection to "true" (one seat selected).
             * Keeps the sidebar clean while nothing is picked. */
            #seat-properties-section[data-has-selection="false"] {
                display: none;
            }
        </style>
    </head>
    <body class="h-full font-sans text-[#2B1113] antialiased">
        <div class="flex h-full flex-col bg-[#FFFBF6] overflow-hidden">
            {{-- ===================== TOPBAR ===================== --}}
            <header class="flex h-14 shrink-0 items-center justify-between border-b border-black/10 bg-white px-3 sm:px-4">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="shrink-0">
                        <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-7 w-auto">
                    </a>
                    <div class="min-w-0 border-l border-black/10 pl-3">
                        <h1 class="truncate font-[Poppins] text-sm font-bold text-[#2B1113]">{{ $busUnit->name }}</h1>
                        <p class="truncate text-[11px] text-[#2B1113]/50">Editor de asientos</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('admin.unidades') }}" id="cancel-button" class="rounded-lg border border-black/10 bg-white px-3 py-1.5 text-xs font-bold text-[#2B1113] hover:bg-black/5 transition-colors">
                        Cancelar
                    </a>
                    <button type="button" id="seat-save" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#F5B301] px-3 py-1.5 text-xs font-bold text-[#2B1113] shadow-sm hover:bg-[#E0A400] transition-colors">
                        Guardar distribución
                    </button>
                </div>
            </header>

            @if (session('success'))
                <div class="shrink-0 border-b border-emerald-200 bg-emerald-50 px-4 py-1.5 text-xs font-medium text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ===================== BODY: SIDEBAR + CANVAS ===================== --}}
            <div class="flex flex-1 overflow-hidden">
                {{-- Vertical collapsible sidebar with all the editor controls.
                     It carries every section that used to live in the two
                     horizontal accordion bars at the top of the page, so
                     the canvas below can stretch the full width/height
                     instead of being squashed into "that little piece"
                     under the tools. --}}
                <aside id="tools-sidebar" data-collapsed="false" class="flex shrink-0 flex-col border-r border-black/10 bg-white" aria-label="Herramientas del editor">
                    <div class="flex shrink-0 items-center justify-between border-b border-black/10 px-2 py-2">
                        <button id="sidebar-toggle" type="button" class="flex h-7 w-7 items-center justify-center rounded-md text-[#2B1113]/60 hover:bg-black/5 hover:text-[#2B1113] transition-colors" title="Colapsar / Expandir panel" aria-label="Colapsar o expandir el panel de herramientas">
                            <svg id="sidebar-toggle-icon" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <span class="sidebar-hide-when-collapsed text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Panel</span>
                    </div>

                    <div class="sidebar-scroll flex-1 overflow-y-auto">
                        {{-- ============== 1. Datos de la unidad ============== --}}
                        <details class="group border-b border-black/5" open>
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 11h8M6 21h12a2 2 0 002-2V7l-5-5H6a2 2 0 00-2 2v15a2 2 0 002 2z"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Datos de la unidad</span>
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3">
                                <form method="POST" action="{{ route('admin.unidades.update', $busUnit) }}" class="space-y-3" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')

                                    <div>
                                        <label for="name" class="mb-1 block text-[11px] font-semibold text-[#2B1113]">Nombre</label>
                                        <input id="name" name="name" type="text" value="{{ old('name', $busUnit->name) }}" class="w-full rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-xs text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                                        @error('name')
                                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="description" class="mb-1 block text-[11px] font-semibold text-[#2B1113]">Descripción</label>
                                        <input id="description" name="description" type="text" value="{{ old('description', $busUnit->description) }}" class="w-full rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-xs text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="canvas_width" class="mb-1 block text-[11px] font-semibold text-[#2B1113]">Ancho</label>
                                            <input id="canvas_width" name="canvas_width" type="number" min="200" value="{{ old('canvas_width', $busUnit->canvas_width) }}" class="w-full rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-xs text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                                        </div>
                                        <div>
                                            <label for="canvas_height" class="mb-1 block text-[11px] font-semibold text-[#2B1113]">Alto</label>
                                            <input id="canvas_height" name="canvas_height" type="number" min="200" value="{{ old('canvas_height', $busUnit->canvas_height) }}" class="w-full rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-xs text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none" required>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        <label class="flex items-center gap-1.5 text-[11px] font-semibold text-[#2B1113]">
                                            <input type="checkbox" name="has_upper_deck" value="1" class="h-3.5 w-3.5 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $busUnit->has_upper_deck ? 'checked' : '' }}>
                                            Doble piso
                                        </label>
                                        <label class="flex items-center gap-1.5 text-[11px] font-semibold text-[#2B1113]">
                                            <input type="checkbox" name="is_active" value="1" class="h-3.5 w-3.5 rounded border-black/20 text-[#8C1D2B] focus:ring-[#8C1D2B]" {{ $busUnit->is_active ? 'checked' : '' }}>
                                            Activa
                                        </label>
                                    </div>

                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#8C1D2B] px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
                                        Guardar datos
                                    </button>
                                </form>
                            </div>
                        </details>

                        {{-- ============== 1.5 Asiento seleccionado ============== --}}
                        {{-- Hidden by default; the JS toggles `data-has-selection`
                             on this section so it only shows the per-seat
                             controls when exactly one seat is selected. --}}
                        <details id="seat-properties-section" class="group border-b border-black/5" data-has-selection="false" open>
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Asiento</span>
                                <span id="seat-properties-label" class="sidebar-hide-when-collapsed inline-flex h-5 min-w-[20px] items-center justify-center rounded-md bg-[#8C1D2B]/10 px-1.5 text-[10px] font-bold text-[#8C1D2B]">—</span>
                                <svg class="sidebar-hide-when-collapsed sidebar-chevron h-3 w-3 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3 space-y-2">
                                <p class="text-[11px] text-[#2B1113]/60">Cambia el tipo de boleto que puede comprar este asiento. Útil para reservar filas de adelante a viajes redondos y de atrás a solo ida.</p>
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Tipo de viaje permitido</span>
                                    <select id="seat-allowed-trip-type" class="mt-1 w-full rounded-lg border border-black/10 bg-white px-2 py-1.5 text-xs focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                        <option value="both">Ambos (ida y redondo)</option>
                                        <option value="one_way">Solo ida</option>
                                        <option value="round_trip">Solo redondo</option>
                                    </select>
                                </label>
                                <p class="text-[10px] text-[#2B1113]/40">Se guarda al pulsar <strong>Guardar distribución</strong>.</p>
                            </div>
                        </details>

                        {{-- ============== 2. Vista ============== --}}
                        <details class="group border-b border-black/5" open>
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Vista</span>
                                <svg class="sidebar-hide-when-collapsed sidebar-chevron h-3 w-3 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3 space-y-2">
                                <div class="flex items-center gap-1">
                                    <button type="button" id="view-zoom-out" class="flex h-7 w-7 items-center justify-center rounded-md bg-white text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5" title="Alejar">−</button>
                                    <span id="view-zoom-label" class="flex-1 text-center text-[11px] font-bold text-[#2B1113]/70">100%</span>
                                    <button type="button" id="view-zoom-in" class="flex h-7 w-7 items-center justify-center rounded-md bg-white text-sm font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5" title="Acercar">+</button>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button type="button" id="view-zoom-reset" class="rounded-md bg-white px-2 py-1.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">Restablecer</button>
                                    <button type="button" id="view-pan-toggle" class="rounded-md bg-white px-2 py-1.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5">Mover vista</button>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button type="button" id="select-all" class="rounded-md bg-white px-2 py-1.5 text-[11px] font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5" title="Ctrl+A">Seleccionar todo</button>
                                    <button type="button" id="selection-delete" class="rounded-md bg-red-50 px-2 py-1.5 text-[11px] font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40" title="Supr/Backspace" disabled>Eliminar</button>
                                </div>
                                @if ($busUnit->has_upper_deck)
                                    <div class="border-t border-black/5 pt-2 mt-2">
                                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/40">Piso</p>
                                        <div class="flex rounded-md bg-white p-0.5 ring-1 ring-black/10">
                                            <button type="button" id="deck-lower" class="deck-tab flex-1 rounded px-2 py-1 text-[11px] font-bold transition-colors">Planta baja</button>
                                            <button type="button" id="deck-upper" class="deck-tab flex-1 rounded px-2 py-1 text-[11px] font-bold transition-colors">Planta alta</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>

                        {{-- ============== 3. Generar grilla ============== --}}
                        <details class="group border-b border-black/5">
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Generar grilla</span>
                                <svg class="sidebar-hide-when-collapsed sidebar-chevron h-3 w-3 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3 space-y-2">
                                <div class="grid grid-cols-3 gap-1.5">
                                    <label class="block">
                                        <span class="block text-[10px] font-semibold text-[#2B1113]/70">Filas</span>
                                        <input type="number" id="grid-rows" min="1" max="20" value="4" class="mt-0.5 w-full rounded-md border border-black/10 bg-white px-2 py-1 text-xs focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </label>
                                    <label class="block">
                                        <span class="block text-[10px] font-semibold text-[#2B1113]/70">Columnas</span>
                                        <input type="number" id="grid-cols" min="1" max="10" value="4" class="mt-0.5 w-full rounded-md border border-black/10 bg-white px-2 py-1 text-xs focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </label>
                                    <label class="block">
                                        <span class="block text-[10px] font-semibold text-[#2B1113]/70">Prefijo</span>
                                        <input type="text" id="grid-prefix" maxlength="4" placeholder="V" class="mt-0.5 w-full rounded-md border border-black/10 bg-white px-2 py-1 text-xs focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    </label>
                                </div>
                                <button type="button" id="grid-generate" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#2B1113] px-3 py-1.5 text-xs font-bold text-white hover:bg-black/80 transition-colors">
                                    Generar
                                </button>
                            </div>
                        </details>

                        {{-- ============== 4. Agregar ============== --}}
                        <details class="group border-b border-black/5">
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Agregar</span>
                                <svg class="sidebar-hide-when-collapsed sidebar-chevron h-3 w-3 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3 space-y-2">
                                <button type="button" id="seat-add" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#8C1D2B] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#6F1622] transition-colors">
                                    + Asiento
                                </button>
                                <select id="object-kind" class="w-full rounded-lg border border-black/10 bg-white px-2 py-1.5 text-xs focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                                    <option value="door">Puerta</option>
                                    <option value="stairs">Escaleras</option>
                                    <option value="driver">Chofer</option>
                                    <option value="bathroom">Baño</option>
                                    <option value="table">Mesa</option>
                                    <option value="other">Otro</option>
                                    <option value="divider">Separador</option>
                                    <option value="outline">Contorno (silueta)</option>
                                </select>
                                <button type="button" id="object-add" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-slate-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-700 transition-colors">
                                    + Objeto
                                </button>
                            </div>
                        </details>

                        {{-- ============== 5. Color contorno ============== --}}
                        <details class="group border-b border-black/5">
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Color contorno</span>
                                <svg class="sidebar-hide-when-collapsed sidebar-chevron h-3 w-3 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3">
                                <input type="color" id="outline-color" value="#2B1113" class="h-8 w-full cursor-pointer rounded-md border border-black/10 bg-white p-0.5 disabled:cursor-not-allowed disabled:opacity-40" disabled title="Selecciona un contorno para cambiar su color">
                            </div>
                        </details>

                        {{-- ============== 6. Plantilla (exportar / importar) ============== --}}
                        <details class="group border-b border-black/5">
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 hover:bg-black/5 transition-colors">
                                <svg class="section-icon h-4 w-4 shrink-0 text-[#2B1113]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                <span class="sidebar-hide-when-collapsed sidebar-label flex-1 text-xs font-bold text-[#2B1113]">Plantilla</span>
                                <svg class="sidebar-hide-when-collapsed sidebar-chevron h-3 w-3 text-[#2B1113]/40 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="border-t border-black/5 bg-[#FFFBF6] p-3 space-y-2">
                                <p class="text-[11px] leading-snug text-[#2B1113]/60">Descarga la distribución completa (forma + acomodo) como archivo JSON, o cárgala en otra unidad para no tener que rehacerla.</p>
                                <button type="button" id="template-export" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#2B1113] px-3 py-1.5 text-xs font-bold text-white hover:bg-black/80 transition-colors">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a.75.75 0 01.75.75v9.69l2.97-2.97a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0l-4.25-4.25a.75.75 0 011.06-1.06l2.97 2.97V3.75A.75.75 0 0110 3z"/><path d="M3.5 14.75a.75.75 0 01.75.75v1.5c0 .14.11.25.25.25h11c.14 0 .25-.11.25-.25v-1.5a.75.75 0 011.5 0v1.5A1.75 1.75 0 0115.5 19h-11A1.75 1.75 0 012.75 17.25v-1.5a.75.75 0 011.5 0z"/></svg>
                                    Exportar plantilla
                                </button>
                                <button type="button" id="template-import-button" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-[#2B1113] ring-1 ring-black/10 hover:bg-black/5 transition-colors">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17a.75.75 0 01-.75-.75V6.56L6.28 9.53a.75.75 0 01-1.06-1.06l4.25-4.25a.75.75 0 011.06 0l4.25 4.25a.75.75 0 11-1.06 1.06L10.75 6.56v9.69A.75.75 0 0110 17z"/><path d="M3.5 5.25a.75.75 0 01.75-.75h11c.14 0 .25.11.25.25v1.5a.75.75 0 001.5 0v-1.5A1.75 1.75 0 0115.5 3h-11A1.75 1.75 0 012.75 4.75v1.5a.75.75 0 001.5 0v-1z"/></svg>
                                    Importar plantilla
                                </button>
                                <input type="file" id="template-import-input" accept=".json,application/json" class="hidden">
                            </div>
                        </details>
                    </div>
                </aside>

                {{-- ===================== CANVAS ===================== --}}
                <main class="relative flex flex-1 flex-col overflow-hidden bg-white">
                    <div id="seat-canvas-wrap" class="relative flex-1">
                        <div id="seat-canvas" class="absolute inset-0 overflow-auto"></div>
                    </div>
                    <p id="seat-status" class="shrink-0 border-t border-black/10 bg-[#FFFBF6] px-3 py-1 text-[11px] font-medium empty:hidden"></p>
                </main>
            </div>
        </div>

        <script>
            window.__SEAT_EDITOR__ = {
                unitName: {!! json_encode($busUnit->name) !!},
                canvasWidth: {{ $busUnit->canvas_width }},
                canvasHeight: {{ $busUnit->canvas_height }},
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
                    'allowed_trip_type' => $s->allowed_trip_type,
                    'color' => $s->color,
                    'pos_x' => $s->pos_x,
                    'pos_y' => $s->pos_y,
                ])) !!},
            };
        </script>
        @vite(['resources/js/seat-editor.js'])
    </body>
</html>
