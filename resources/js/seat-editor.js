import Konva from 'konva';
import axios from 'axios';

const config = window.__SEAT_EDITOR__;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const SEAT_COLORS = {
    normal: { fill: '#FFFFFF', stroke: '#8C1D2B' },
    vip: { fill: '#F5B301', stroke: '#8C6B00' },
    disabled: { fill: '#D1D5DB', stroke: '#6B7280' },
};
const OBJECT_COLORS = { fill: '#94A3B8', stroke: '#475569' };
const SELECTED_STROKE = '#16A34A';
const OUTLINE_DEFAULT_COLOR = '#2B1113';

const OBJECT_DEFAULT_LABELS = {
    door: 'PUERTA',
    stairs: 'ESCALERAS',
    driver: 'CHOFER',
    bathroom: 'BAÑO',
    table: 'MESA',
    other: 'OBJETO',
    outline: 'CONTORNO',
    // Purely decorative — a divider/panel (window dividers, partitions,
    // "vitrales") that never needs a label of its own.
    divider: 'SEP',
};

// Objects render a flat, single-color pictogram instead of spelled-out text
// — reads like real signage (restroom/exit/stairs signs) instead of relying
// on a platform's colorful emoji font. Seats keep real text (their folio/
// number); "Otro" keeps its text too (there's no universal pictogram for
// "something else"); the divider has neither, just a blank panel.
const ICON_TYPES = new Set(['door', 'stairs', 'driver', 'bathroom', 'table']);

// Each icon is a handful of primitive shapes drawn in a single flat color
// (the object's current stroke color, so it goes green like everything else
// when selected) — no external assets, crisp at any zoom level.
function buildIconNode(type, width, height, color) {
    const group = new Konva.Group({ name: 'seat-icon', listening: false });
    const cx = width / 2;
    const cy = height / 2;
    const s = Math.min(width, height) * 0.62;

    if (type === 'door') {
        group.add(new Konva.Rect({
            x: cx - s * 0.28, y: cy - s * 0.42, width: s * 0.56, height: s * 0.84,
            cornerRadius: 1, stroke: color, strokeWidth: Math.max(1.5, s * 0.07), fill: 'transparent',
        }));
        group.add(new Konva.Circle({ x: cx + s * 0.14, y: cy, radius: Math.max(1.3, s * 0.05), fill: color }));
    } else if (type === 'stairs') {
        const steps = 4;
        const stepW = s / steps;
        for (let i = 0; i < steps; i++) {
            group.add(new Konva.Rect({
                x: cx - s / 2 + i * stepW,
                y: cy + s / 2 - (i + 1) * stepW,
                width: stepW + 0.5,
                height: (i + 1) * stepW,
                fill: color,
            }));
        }
    } else if (type === 'driver') {
        const r = s * 0.42;
        group.add(new Konva.Circle({ x: cx, y: cy, radius: r, stroke: color, strokeWidth: Math.max(1.5, s * 0.08), fill: 'transparent' }));
        group.add(new Konva.Circle({ x: cx, y: cy, radius: Math.max(1.3, s * 0.06), fill: color }));
        [90, 210, 330].forEach((deg) => {
            const rad = (deg * Math.PI) / 180;
            group.add(new Konva.Line({
                points: [cx, cy, cx + Math.cos(rad) * r, cy + Math.sin(rad) * r],
                stroke: color,
                strokeWidth: Math.max(1.5, s * 0.07),
            }));
        });
    } else if (type === 'bathroom') {
        group.add(new Konva.Circle({ x: cx, y: cy - s * 0.32, radius: s * 0.14, fill: color }));
        group.add(new Konva.Line({
            points: [
                cx - s * 0.2, cy + s * 0.38,
                cx - s * 0.13, cy - s * 0.04,
                cx + s * 0.13, cy - s * 0.04,
                cx + s * 0.2, cy + s * 0.38,
            ],
            closed: true,
            fill: color,
        }));
    } else if (type === 'table') {
        group.add(new Konva.Rect({ x: cx - s / 2, y: cy - s * 0.35, width: s, height: s * 0.16, cornerRadius: 1, fill: color }));
        group.add(new Konva.Rect({ x: cx - s * 0.4, y: cy - s * 0.19, width: s * 0.08, height: s * 0.55, fill: color }));
        group.add(new Konva.Rect({ x: cx + s * 0.32, y: cy - s * 0.19, width: s * 0.08, height: s * 0.55, fill: color }));
    }

    return group;
}

// Outline objects draw the bus body's silhouette behind everything else, so
// they get a faint tint of their own border color instead of a solid fill —
// enough to read as "inside the bus" without ever visually competing with
// the seats drawn on top of them.
function hexToRgba(hex, alpha) {
    const clean = (hex || OUTLINE_DEFAULT_COLOR).replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const SEAT_SIZE = 40;
const GRID_GAP = 14;
const GRID_ORIGIN = { x: 30, y: 30 };
const MIN_ZOOM = 0.25;
const MAX_ZOOM = 3;

const canvasWrap = document.getElementById('seat-canvas-wrap');

function stageSize() {
    // Fill all the space the page actually gives the canvas, but never go
    // smaller than the unit's configured size (Ancho/Alto in "Datos de la unidad").
    return {
        width: Math.max(config.canvasWidth, canvasWrap.clientWidth),
        height: Math.max(config.canvasHeight, canvasWrap.clientHeight),
    };
}

const stage = new Konva.Stage({
    container: 'seat-canvas',
    ...stageSize(),
});
const layer = new Konva.Layer();
const uiLayer = new Konva.Layer();
stage.add(layer);
stage.add(uiLayer);

// Resize handles for whatever single shape is selected — lets the admin
// drag the sides/corners of a seat, object, or the "Contorno" outline
// directly, instead of typing width/height numbers.
const transformer = new Konva.Transformer({
    rotateEnabled: false,
    enabledAnchors: ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'],
    boundBoxFunc: (oldBox, newBox) => (newBox.width < 10 || newBox.height < 10 ? oldBox : newBox),
    // No border rectangle: it would sit right on top of the shape and, since
    // groups are no longer Konva-draggable (see buildSeatGroup), it would
    // swallow clicks meant for our own custom drag logic underneath. The
    // green selection stroke we already draw on the shape doubles as the
    // "this is selected" outline, so nothing is lost visually.
    borderEnabled: false,
});
uiLayer.add(transformer);

// Konva.Transformer resizes by scaling the node instead of changing its
// width/height — fine as a live preview, but we want the seat's actual
// stored width/height to change (so borders/labels stay crisp and the
// server sees a real size). Bake the scale into the size once, then reset
// it back to 1 and rebuild the shape at that true size.
function commitTransform(group) {
    const scaleX = group.scaleX();
    const scaleY = group.scaleY();
    if (scaleX === 1 && scaleY === 1) return;

    const width = Math.max(10, Math.round(group.getAttr('seatWidth') * scaleX));
    const height = Math.max(10, Math.round(group.getAttr('seatHeight') * scaleY));

    group.scale({ x: 1, y: 1 });
    group.setAttr('seatWidth', width);
    group.setAttr('seatHeight', height);
    renderShapeVisuals(group);
    layer.batchDraw();
}

// Konva's Transformer fires 'transformend' directly on itself/the resized
// node via the internal non-bubbling _fire() — it never reaches a delegated
// stage.on('transformend', ...) listener. Attaching directly to the
// transformer (which lives for the whole session, unlike individual seat
// groups) is what actually catches it.
transformer.on('transformend', (e) => {
    const group = findSeatGroup(e.target);
    if (!group) return;
    commitTransform(group);
});

function resizeStageToContainer() {
    const size = stageSize();
    stage.width(size.width);
    stage.height(size.height);
    stage.batchDraw();
}

const toolsSidebar = document.getElementById('tools-sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const cancelButton = document.getElementById('cancel-button');

window.addEventListener('resize', resizeStageToContainer);

// The sidebar animates its width in/out, so the canvas wrap only reaches
// its final size once the CSS transition has finished — re-measure after
// the same 200ms we set on the transition to keep the stage in sync.
function handleSidebarToggle() {
    setTimeout(resizeStageToContainer, 220);
}
sidebarToggle?.addEventListener('click', () => {
    if (!toolsSidebar) return;
    const isCollapsed = toolsSidebar.getAttribute('data-collapsed') === 'true';
    toolsSidebar.setAttribute('data-collapsed', isCollapsed ? 'false' : 'true');
    handleSidebarToggle();
});

// Warn before discarding local Konva state — a regular "Cancel" link would
// silently throw away any newly added / moved / resized seats that haven't
// been saved yet, which is exactly the kind of "where did my work go"
// moment a confirmation prompt is supposed to prevent.
if (cancelButton) {
    cancelButton.addEventListener('click', (evt) => {
        if (!window.confirm('¿Descartar los cambios no guardados del lienzo?')) {
            evt.preventDefault();
        }
    });
}

const addButton = document.getElementById('seat-add');
const objectAddButton = document.getElementById('object-add');
const objectKindSelect = document.getElementById('object-kind');
const saveButton = document.getElementById('seat-save');
const statusEl = document.getElementById('seat-status');
const gridRowsInput = document.getElementById('grid-rows');
const gridColsInput = document.getElementById('grid-cols');
const gridPrefixInput = document.getElementById('grid-prefix');
const gridGenerateButton = document.getElementById('grid-generate');
const deckLowerTab = document.getElementById('deck-lower');
const deckUpperTab = document.getElementById('deck-upper');
const zoomOutButton = document.getElementById('view-zoom-out');
const zoomInButton = document.getElementById('view-zoom-in');
const zoomResetButton = document.getElementById('view-zoom-reset');
const zoomLabel = document.getElementById('view-zoom-label');
const panToggleButton = document.getElementById('view-pan-toggle');
const selectAllButton = document.getElementById('select-all');
const deleteSelectionButton = document.getElementById('selection-delete');
const outlineColorInput = document.getElementById('outline-color');
const seatAllowedTripTypeSelect = document.getElementById('seat-allowed-trip-type');

// Per-seat "Tipo de viaje permitido" — change handler is wired once at
// module load and mutates the single selected seat's allowedTripType
// attribute (the actual persistence happens when the user hits
// "Guardar distribución" later).
if (seatAllowedTripTypeSelect) {
    seatAllowedTripTypeSelect.addEventListener('change', () => {
        if (selectedGroups.size !== 1) return;
        const [only] = selectedGroups;
        only.setAttr('allowedTripType', seatAllowedTripTypeSelect.value);
    });
}

// The status bar lives at the bottom of the canvas column in the new
// layout, so its base classes (border, padding, font size) are owned by
// the HTML. Toggling only the semantic color class via classList keeps
// those base styles intact instead of wiping them with a className
// reassignment every time we have something to say.
const STATUS_COLOR_CLASSES = ['text-red-600', 'text-emerald-600', 'text-[#2B1113]/60'];
function setStatus(message, colorClass = 'text-[#2B1113]/60') {
    statusEl.textContent = message ?? '';
    statusEl.classList.remove(...STATUS_COLOR_CLASSES);
    if (message) statusEl.classList.add(colorClass);
}

function reportError(context, error) {
    console.error(context, error);
    setStatus(`${context}: ${error?.message ?? error}`, 'text-red-600');
}

let tempIdCounter = 0;
let currentDeck = 'lower';
let panMode = false;
const selectedGroups = new Set();
let pendingClickNarrowGroup = null;

function allSeatGroups() {
    return layer.getChildren((node) => node instanceof Konva.Group);
}

// The "Contorno" outline is a giant rectangle that spans nearly the whole
// canvas by design, so a bounding-box-based rubber-band drawn over a handful
// of seats almost always overlaps it too. Sweeping it into every marquee/
// select-all would silently drag or delete the whole bus silhouette along
// with whatever the admin actually meant to select — it stays selectable by
// a direct click, just excluded from the "select everything in this area"
// tools.
function isSweepSelectable(group) {
    return group.getAttr('seatType') !== 'outline';
}

// Outline and divider objects are background decoration — they must never
// visually cover, or steal clicks from, an actual seat/object drawn on top
// of them, regardless of the order they came back from the server in or
// how closely the admin positions them. moveToBottom() puts whatever it's
// called on LAST at the very back, so dividers go first (ending up just
// above the outline) and the outline goes last (ending up furthest back of
// all) — both still behind every seat either way.
function sendBackgroundObjectsToBack() {
    allSeatGroups()
        .filter((group) => group.getAttr('seatType') === 'divider')
        .forEach((group) => group.moveToBottom());
    allSeatGroups()
        .filter((group) => group.getAttr('seatType') === 'outline')
        .forEach((group) => group.moveToBottom());
}

// Every pointer/drag event delegated to the stage hands us whatever child
// shape was actually hit (a Rect/Ellipse/Text) — walk up to the seat Group
// that owns it, which is where all the seat data (attrs) actually lives.
function findSeatGroup(node) {
    let current = node;
    while (current && current !== layer && current !== stage) {
        if (current.getAttr('seatId') !== undefined) return current;
        current = current.getParent();
    }
    return null;
}

function colorsFor(kind, type, color) {
    if (kind === 'object' && type === 'outline') {
        const stroke = color || OUTLINE_DEFAULT_COLOR;
        return { fill: hexToRgba(stroke, 0.05), stroke };
    }
    if (kind === 'object' && type === 'divider') {
        // A "vitral"-style glass panel, not a solid block — it needs to read
        // as a subtle divider even when placed right up against (or slightly
        // overlapping) a seat, never as an opaque box hiding what's under it.
        return { fill: hexToRgba(OBJECT_COLORS.stroke, 0.12), stroke: hexToRgba(OBJECT_COLORS.stroke, 0.5) };
    }
    if (kind === 'object') return OBJECT_COLORS;
    return SEAT_COLORS[type] ?? SEAT_COLORS.normal;
}

function uniqueLabel(baseLabel, excludeGroup = null) {
    const existing = new Set(
        allSeatGroups()
            .filter((group) => group.getAttr('seatDeck') === currentDeck && group !== excludeGroup)
            .map((group) => group.getAttr('seatLabel'))
    );

    if (!existing.has(baseLabel)) return baseLabel;

    let suffix = 2;
    while (existing.has(`${baseLabel}-${suffix}`)) {
        suffix += 1;
    }
    return `${baseLabel}-${suffix}`;
}

function renameGroup(group) {
    const currentLabel = group.getAttr('seatLabel');
    const nextLabel = window.prompt('Nuevo número/folio:', currentLabel);
    if (nextLabel === null) return;

    const trimmed = nextLabel.trim();
    if (!trimmed || trimmed === currentLabel) return;

    group.setAttr('seatLabel', uniqueLabel(trimmed, group));
    renderShapeVisuals(group);
    layer.batchDraw();
}

// Mutates the seat's existing shape/text nodes in place whenever possible
// (color, size, label, selection state) instead of destroying and rebuilding
// them on every refresh — only a real rect↔circle change actually needs a
// new node. With dozens of seats on screen this keeps selection/dragging smooth.
function renderShapeVisuals(group) {
    const colors = colorsFor(group.getAttr('seatKind'), group.getAttr('seatType'), group.getAttr('seatColor'));
    const shape = group.getAttr('seatShape');
    const width = group.getAttr('seatWidth');
    const height = group.getAttr('seatHeight');
    const borderWidth = group.getAttr('borderWidth');
    const isSelected = selectedGroups.has(group);
    const stroke = isSelected ? SELECTED_STROKE : colors.stroke;
    const strokeWidth = isSelected ? Math.max(4, borderWidth + 2) : borderWidth;

    const wantsEllipse = shape === 'circle';
    let shapeNode = group.findOne('.seat-rect');

    if (shapeNode && ((wantsEllipse && shapeNode.getClassName() !== 'Ellipse') || (!wantsEllipse && shapeNode.getClassName() !== 'Rect'))) {
        shapeNode.destroy();
        shapeNode = null;
    }

    if (!shapeNode) {
        shapeNode = wantsEllipse ? new Konva.Ellipse({ name: 'seat-rect' }) : new Konva.Rect({ name: 'seat-rect' });
        group.add(shapeNode);
        shapeNode.moveToBottom();
    }

    // Selection is shown with a bold green stroke only — no drop shadow.
    // Konva has to recompute a shadow buffer per shape per redraw, which
    // gets noticeably expensive (stutter/freeze) once several dozen seats
    // are selected and dragged together at once.
    if (wantsEllipse) {
        shapeNode.setAttrs({ x: width / 2, y: height / 2, radiusX: width / 2, radiusY: height / 2, fill: colors.fill, stroke, strokeWidth, shadowEnabled: false });
    } else {
        shapeNode.setAttrs({ x: 0, y: 0, width, height, fill: colors.fill, stroke, strokeWidth, cornerRadius: group.getAttr('cornerRadius'), shadowEnabled: false });
    }

    let text = group.findOne('.seat-text');
    if (!text) {
        text = new Konva.Text({
            name: 'seat-text',
            align: 'center',
            verticalAlign: 'middle',
            fontSize: 10,
            fontStyle: 'bold',
            fill: '#2B1113',
            listening: false,
        });
        group.add(text);
    }

    const kind = group.getAttr('seatKind');
    const type = group.getAttr('seatType');
    const wantsIcon = kind === 'object' && ICON_TYPES.has(type);

    group.findOne('.seat-icon')?.destroy();

    if (wantsIcon) {
        text.text('');
        group.add(buildIconNode(type, width, height, stroke));
    } else {
        const isDivider = kind === 'object' && type === 'divider';
        const isOutline = kind === 'object' && type === 'outline';
        // The outline's own label is irrelevant to riders — it always shows
        // the unit's number so they can tell which bus map they're looking at.
        const displayText = isDivider ? '' : isOutline ? (config.unitName ?? group.getAttr('seatLabel')) : group.getAttr('seatLabel');
        text.setAttrs({ text: displayText, width, height });
    }
}

function buildSeatGroup(seat) {
    // Translation is handled entirely by our own mousedown/mousemove/mouseup
    // logic below (not Konva's built-in per-node dragging) so a multi-select
    // drag is always exactly "move every selected group by the same delta" —
    // one single code path, instead of Konva's native drag on one node plus
    // our code trying to keep the rest in sync with it.
    const group = new Konva.Group({
        x: seat.pos_x,
        y: seat.pos_y,
        draggable: false,
    });

    group.setAttr('seatId', seat.id);
    group.setAttr('seatLabel', seat.label);
    group.setAttr('seatKind', seat.kind ?? 'seat');
    group.setAttr('seatType', seat.type);
    group.setAttr('seatDeck', seat.deck ?? 'lower');
    group.setAttr('seatShape', seat.shape ?? 'rect');
    group.setAttr('seatWidth', seat.width ?? SEAT_SIZE);
    group.setAttr('seatHeight', seat.height ?? SEAT_SIZE);
    group.setAttr('cornerRadius', seat.corner_radius ?? 8);
    group.setAttr('allowedTripType', seat.allowed_trip_type ?? 'both');
    group.setAttr('borderWidth', seat.border_width ?? 2);
    group.setAttr('seatColor', seat.color ?? null);
    group.visible((seat.deck ?? 'lower') === currentDeck);

    renderShapeVisuals(group);

    return group;
}

function refreshSeatVisual(group) {
    renderShapeVisuals(group);
}

function syncSelectionUI() {
    deleteSelectionButton.disabled = selectedGroups.size === 0;
    outlineColorInput.disabled = true;

    // The resize handles only make sense for exactly one shape at a time —
    // with several selected there's no single width/height to drag.
    if (selectedGroups.size === 1) {
        const [only] = selectedGroups;
        transformer.nodes([only]);

        if (only.getAttr('seatKind') === 'object' && only.getAttr('seatType') === 'outline') {
            outlineColorInput.disabled = false;
            outlineColorInput.value = only.getAttr('seatColor') || OUTLINE_DEFAULT_COLOR;
        }

        // Populate the per-seat "Tipo de viaje permitido" editor. Only
        // the editor is read for now — the change handler is wired once
        // at module load (it captures the live `selectedGroups` set, so
        // we just need to keep the dropdown's value in sync with the
        // selected Konva group here).
        const section = document.getElementById('seat-properties-section');
        const labelBadge = document.getElementById('seat-properties-label');
        if (section && labelBadge) {
            section.setAttribute('data-has-selection', 'true');
            labelBadge.textContent = only.getAttr('seatLabel') || '—';
        }
        if (seatAllowedTripTypeSelect) {
            seatAllowedTripTypeSelect.value = only.getAttr('allowedTripType') ?? 'both';
        }
    } else {
        transformer.nodes([]);
        const section = document.getElementById('seat-properties-section');
        const labelBadge = document.getElementById('seat-properties-label');
        if (section) section.setAttribute('data-has-selection', 'false');
        if (labelBadge) labelBadge.textContent = '—';
    }
    uiLayer.batchDraw();
}

function selectOnly(group) {
    selectedGroups.forEach((g) => {
        selectedGroups.delete(g);
        refreshSeatVisual(g);
    });
    selectedGroups.add(group);
    refreshSeatVisual(group);
    layer.batchDraw();
    syncSelectionUI();
}

function clearSelection() {
    // Empty the set BEFORE repainting — refreshSeatVisual reads
    // selectedGroups.has(group) to decide the stroke color, so repainting
    // while a group is still technically "in" the set just redraws it green
    // again and the highlight never actually clears.
    const previouslySelected = Array.from(selectedGroups);
    selectedGroups.clear();
    previouslySelected.forEach((g) => refreshSeatVisual(g));
    layer.batchDraw();
    syncSelectionUI();
}

function deleteSelected() {
    if (selectedGroups.size === 0) return;
    selectedGroups.forEach((group) => group.destroy());
    selectedGroups.clear();
    layer.draw();
    syncSelectionUI();
}

function selectAllVisible() {
    const visible = allSeatGroups().filter((group) => group.visible() && isSweepSelectable(group));
    if (visible.length === 0) return;

    selectedGroups.forEach((g) => selectedGroups.delete(g));
    visible.forEach((group) => selectedGroups.add(group));
    visible.forEach((group) => refreshSeatVisual(group));
    layer.batchDraw();
    syncSelectionUI();
}

function handleEditorKeydown(e) {
    const tag = document.activeElement?.tagName;
    const isTypingInField = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';

    if ((e.key === 'Delete' || e.key === 'Backspace') && !isTypingInField && selectedGroups.size > 0) {
        e.preventDefault();
        deleteSelected();
        return;
    }

    // Reliable "select everything" that works no matter how zoomed out the
    // canvas is — dragging a precise marquee over tiny, tightly packed seats
    // is hard at low zoom, so this is the guaranteed fallback.
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a' && !isTypingInField) {
        e.preventDefault();
        selectAllVisible();
    }
}

document.addEventListener('keydown', handleEditorKeydown);
selectAllButton.addEventListener('click', selectAllVisible);
deleteSelectionButton.addEventListener('click', deleteSelected);

// Clicking anywhere on the page outside the canvas also clears the current
// selection — not just clicking empty canvas space. The sidebar is excluded
// because its buttons/inputs (Eliminar selección, Color contorno, ...) act
// ON the current selection and would otherwise lose it right before running.
function handleDocumentClickOutside(evt) {
    if (selectedGroups.size === 0) return;
    if (canvasWrap.contains(evt.target)) return;
    if (toolsSidebar && toolsSidebar.contains(evt.target)) return;

    clearSelection();
}

document.addEventListener('click', handleDocumentClickOutside);

function switchDeck(deck) {
    clearSelection();
    currentDeck = deck;

    allSeatGroups().forEach((group) => {
        group.visible(group.getAttr('seatDeck') === deck);
    });

    if (deckLowerTab && deckUpperTab) {
        const activeClasses = ['bg-[#8C1D2B]', 'text-white'];
        const inactiveClasses = ['text-[#2B1113]/60'];

        deckLowerTab.classList.remove(...activeClasses, ...inactiveClasses);
        deckUpperTab.classList.remove(...activeClasses, ...inactiveClasses);
        deckLowerTab.classList.add(...(deck === 'lower' ? activeClasses : inactiveClasses));
        deckUpperTab.classList.add(...(deck === 'upper' ? activeClasses : inactiveClasses));
    }

    layer.draw();
}

config.seats.forEach((seat) => layer.add(buildSeatGroup(seat)));
sendBackgroundObjectsToBack();

layer.draw();

if (deckLowerTab && deckUpperTab) {
    deckLowerTab.addEventListener('click', () => switchDeck('lower'));
    deckUpperTab.addEventListener('click', () => switchDeck('upper'));
    switchDeck('lower');
}

/* ---------- Zoom & pan ---------- */

function updateZoomLabel() {
    zoomLabel.textContent = `${Math.round(stage.scaleX() * 100)}%`;
}

function zoomStageTo(newScale, pointer) {
    const clamped = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, newScale));
    const oldScale = stage.scaleX();
    const point = pointer ?? { x: stage.width() / 2, y: stage.height() / 2 };

    const mousePointTo = {
        x: (point.x - stage.x()) / oldScale,
        y: (point.y - stage.y()) / oldScale,
    };

    stage.scale({ x: clamped, y: clamped });

    stage.position({
        x: point.x - mousePointTo.x * clamped,
        y: point.y - mousePointTo.y * clamped,
    });

    stage.batchDraw();
    updateZoomLabel();
}

zoomInButton.addEventListener('click', () => zoomStageTo(stage.scaleX() * 1.2));
zoomOutButton.addEventListener('click', () => zoomStageTo(stage.scaleX() / 1.2));
zoomResetButton.addEventListener('click', () => {
    stage.scale({ x: 1, y: 1 });
    stage.position({ x: 0, y: 0 });
    stage.batchDraw();
    updateZoomLabel();
});

stage.on('wheel', (e) => {
    e.evt.preventDefault();
    const scaleBy = e.evt.deltaY > 0 ? 1 / 1.05 : 1.05;
    zoomStageTo(stage.scaleX() * scaleBy, stage.getPointerPosition());
});

panToggleButton.addEventListener('click', () => {
    panMode = !panMode;
    panToggleButton.textContent = panMode ? 'Modo: Mover vista' : 'Modo: Seleccionar';
    stage.container().style.cursor = panMode ? 'grab' : 'default';
});

/* ---------- Rubber-band multi-select + pan + group-drag ----------
 * Marquee-select, panning the canvas, and moving whatever is selected are
 * ALL driven by our own mousedown/mousemove/mouseup on the stage — not
 * Konva's built-in per-node dragging. That keeps it to one code path
 * ("figure out which gesture this is, then move whatever needs to move by
 * the same pixel delta") instead of Konva natively dragging only the one
 * node under the cursor while separate code tries to keep the rest of the
 * selection in sync with it.
 */

let selectionRect = null;
let selectionStart = null;
let isSelecting = false;

let isPanning = false;
let panPointerOrigin = null;
let panStageOrigin = null;
let spaceHeld = false;

let dragCandidate = null;
let dragOrigin = null;
let isDraggingGroup = false;
let dragStartPositions = null;
const DRAG_THRESHOLD = 3;

function destroySelectionRect() {
    selectionRect?.destroy();
    selectionRect = null;
    uiLayer.batchDraw();
}

function finalizeSelectionRect() {
    if (!selectionRect) return null;
    // The marquee rect lives on uiLayer, but we draw it in stage-local
    // coordinates (set from getRelativePointerPosition), and the seats we
    // want to hit-test against also store their position in stage-local
    // coords on each Group. Reading the rect's own x/y/width/height
    // directly avoids any getClientRect quirks with stroke widths or
    // ancestor transforms that can otherwise shave a pixel or two off the
    // box at high zoom and miss the seats sitting on the marquee edge.
    const box = {
        x: selectionRect.x(),
        y: selectionRect.y(),
        width: selectionRect.width(),
        height: selectionRect.height(),
    };
    destroySelectionRect();
    return box;
}

function applySelectionBox(box, shiftKey) {
    const matches = allSeatGroups().filter((group) => {
        if (!group.visible() || !isSweepSelectable(group)) return false;
        // Same reasoning as finalizeSelectionRect: compare in the same
        // stage-local space the marquee was drawn in, using each Group's
        // own x/y + its true seatWidth/seatHeight. Going through
        // getClientRect adds a stroke-width sliver that grows with the
        // zoom level and makes the sweep select-or-not decision depend on
        // subpixel rounding rather than what the admin actually drew.
        const gx = group.x();
        const gy = group.y();
        const gw = group.getAttr('seatWidth') ?? SEAT_SIZE;
        const gh = group.getAttr('seatHeight') ?? SEAT_SIZE;
        return !(
            gx + gw < box.x ||
            box.x + box.width < gx ||
            gy + gh < box.y ||
            box.y + box.height < gy
        );
    });

    // Same ordering rule as clearSelection(): figure out every group whose
    // visual needs to change, mutate selectedGroups completely first, and
    // only then repaint — otherwise a group being deselected here still
    // reads as selected mid-loop and gets redrawn green instead of reset.
    const affected = new Set(matches);
    if (!shiftKey) {
        selectedGroups.forEach((g) => affected.add(g));
        selectedGroups.clear();
    }
    matches.forEach((group) => selectedGroups.add(group));
    affected.forEach((group) => refreshSeatVisual(group));
    layer.batchDraw();
    syncSelectionUI();
}

// Holding Space, holding the middle mouse button, or the "Modo: Mover vista"
// toggle all pan — from anywhere, even starting on top of a seat, so you're
// never fighting the tool to just look around the canvas.
function shouldPan(e) {
    return panMode || spaceHeld || (e.evt && e.evt.button === 1);
}

function startPan() {
    isPanning = true;
    panPointerOrigin = stage.getPointerPosition();
    panStageOrigin = { x: stage.x(), y: stage.y() };
    stage.container().style.cursor = 'grabbing';
}

function updatePan() {
    const pos = stage.getPointerPosition();
    if (!pos || !panPointerOrigin) return;
    stage.position({
        x: panStageOrigin.x + (pos.x - panPointerOrigin.x),
        y: panStageOrigin.y + (pos.y - panPointerOrigin.y),
    });
    stage.batchDraw();
}

function endPan() {
    isPanning = false;
    panPointerOrigin = null;
    panStageOrigin = null;
    stage.container().style.cursor = spaceHeld || panMode ? 'grab' : 'default';
}

function beginGroupDrag(group) {
    dragCandidate = group;
    dragOrigin = stage.getRelativePointerPosition();
    isDraggingGroup = false;
    dragStartPositions = new Map();
    selectedGroups.forEach((g) => dragStartPositions.set(g, { x: g.x(), y: g.y() }));
}

function updateGroupDrag() {
    const pos = stage.getRelativePointerPosition();
    const delta = { x: pos.x - dragOrigin.x, y: pos.y - dragOrigin.y };

    if (!isDraggingGroup) {
        if (Math.abs(delta.x) < DRAG_THRESHOLD && Math.abs(delta.y) < DRAG_THRESHOLD) return;
        isDraggingGroup = true;
        pendingClickNarrowGroup = null;
        stage.container().style.cursor = 'move';
    }

    dragStartPositions.forEach((start, g) => {
        g.position({ x: start.x + delta.x, y: start.y + delta.y });
    });
    layer.batchDraw();
}

function endGroupDrag() {
    if (isDraggingGroup) {
        stage.container().style.cursor = 'default';
    }
    dragCandidate = null;
    dragOrigin = null;
    isDraggingGroup = false;
    dragStartPositions = null;
}

stage.on('mousedown touchstart', (e) => {
    if (shouldPan(e)) {
        if (e.evt) e.evt.preventDefault();
        startPan();
        return;
    }

    if (e.target === stage) {
        // Defensive cleanup: if a previous drag's rectangle was somehow
        // never cleared (missed mouseup), destroy it now instead of
        // leaking another orphaned ghost rectangle on top of it.
        destroySelectionRect();

        isSelecting = true;
        selectionStart = stage.getRelativePointerPosition();

        selectionRect = new Konva.Rect({
            x: selectionStart.x,
            y: selectionStart.y,
            width: 0,
            height: 0,
            fill: 'rgba(140, 29, 43, 0.1)',
            stroke: '#8C1D2B',
            strokeWidth: 1,
            dash: [4, 4],
            listening: false,
        });
        uiLayer.add(selectionRect);
        return;
    }

    const group = findSeatGroup(e.target);
    if (!group) return;

    const shiftKey = !!(e.evt && e.evt.shiftKey);
    pendingClickNarrowGroup = null;

    if (shiftKey) {
        if (selectedGroups.has(group)) {
            selectedGroups.delete(group);
        } else {
            selectedGroups.add(group);
        }
        refreshSeatVisual(group);
        layer.batchDraw();
        syncSelectionUI();
        return;
    }

    if (!selectedGroups.has(group)) {
        selectOnly(group);
    } else if (selectedGroups.size > 1) {
        // Already part of a multi-selection: keep the whole selection
        // intact in case this turns into a group-drag. If it turns out
        // to be a plain click (no drag), mouseup below narrows it down.
        pendingClickNarrowGroup = group;
    }

    beginGroupDrag(group);
});

stage.on('mousemove touchmove', () => {
    if (isPanning) {
        updatePan();
        return;
    }

    if (isSelecting && selectionRect) {
        const pos = stage.getRelativePointerPosition();
        selectionRect.setAttrs({
            x: Math.min(selectionStart.x, pos.x),
            y: Math.min(selectionStart.y, pos.y),
            width: Math.abs(pos.x - selectionStart.x),
            height: Math.abs(pos.y - selectionStart.y),
        });
        uiLayer.batchDraw();
        return;
    }

    if (dragCandidate) {
        updateGroupDrag();
    }
});

stage.on('mouseup touchend', (e) => {
    if (isPanning) {
        endPan();
        return;
    }

    if (isSelecting) {
        isSelecting = false;
        const box = finalizeSelectionRect();
        const dragged = box && (box.width > 3 || box.height > 3);
        if (dragged) {
            applySelectionBox(box, e.evt.shiftKey);
        } else {
            // Plain click on empty canvas: clear the selection, even if a
            // ghost rectangle or stale selection somehow survived above.
            clearSelection();
        }
        return;
    }

    if (dragCandidate) {
        const wasRealDrag = isDraggingGroup;
        const candidate = dragCandidate;
        endGroupDrag();

        // No actual drag happened (just a click) and this seat was already
        // part of a bigger selection: narrow down to just this one.
        if (!wasRealDrag && pendingClickNarrowGroup === candidate && !(e.evt && e.evt.shiftKey)) {
            selectOnly(candidate);
        }
        pendingClickNarrowGroup = null;
    }
});

stage.on('dblclick dbltap', (e) => {
    const group = findSeatGroup(e.target);
    if (!group) return;

    selectOnly(group);
    renameGroup(group);
});

function handleSpaceKeydown(e) {
    if (e.code !== 'Space' || spaceHeld) return;
    const tag = document.activeElement?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

    e.preventDefault();
    spaceHeld = true;
    if (!isPanning) stage.container().style.cursor = 'grab';
}

function handleSpaceKeyup(e) {
    if (e.code !== 'Space') return;
    spaceHeld = false;
    if (!isPanning) stage.container().style.cursor = panMode ? 'grab' : 'default';
}

document.addEventListener('keydown', handleSpaceKeydown);
document.addEventListener('keyup', handleSpaceKeyup);

// Fallback for when the mouse/finger is released outside the canvas element
// (e.g. dragged past the edge) — the Konva stage never gets its own mouseup
// in that case, which would otherwise leave a marquee/pan/drag stuck forever.
// Catch the release at the document level too.
function handleGlobalPointerUp(evt) {
    if (isPanning) {
        endPan();
        return;
    }

    if (isSelecting) {
        isSelecting = false;
        const box = finalizeSelectionRect();
        const dragged = box && (box.width > 3 || box.height > 3);
        if (dragged) {
            applySelectionBox(box, !!evt.shiftKey);
        }
        return;
    }

    if (dragCandidate) {
        endGroupDrag();
        pendingClickNarrowGroup = null;
    }
}

document.addEventListener('mouseup', handleGlobalPointerUp);
document.addEventListener('touchend', handleGlobalPointerUp);
document.addEventListener('mouseleave', handleGlobalPointerUp);
window.addEventListener('blur', handleGlobalPointerUp);

// Ultimate safety net: if we still think a gesture is active but the mouse
// button is no longer actually held down (e.buttons === 0), the browser
// simply never delivered a mouseup/mouseleave/blur event to us — this
// catches that on the very next pointer movement anywhere on the page.
function handleGlobalMouseMoveSafetyNet(evt) {
    if ((isSelecting || isPanning || dragCandidate) && evt.buttons === 0) {
        handleGlobalPointerUp(evt);
    }
}

document.addEventListener('mousemove', handleGlobalMouseMoveSafetyNet);

/* ---------- Add seat / add object ---------- */

function spawnPosition(width, height) {
    // Center of the currently visible viewport, in layer-local coordinates
    // (accounts for current zoom/pan), with a small cascade offset so
    // repeated clicks don't stack new items exactly on top of each other.
    const scale = stage.scaleX() || 1;
    const centerX = (stage.width() / 2 - stage.x()) / scale;
    const centerY = (stage.height() / 2 - stage.y()) / scale;
    const cascade = (tempIdCounter % 6) * 16;

    return {
        pos_x: Math.max(0, centerX - width / 2 + cascade),
        pos_y: Math.max(0, centerY - height / 2 + cascade),
    };
}

addButton.addEventListener('click', () => {
    try {
        tempIdCounter += 1;

        const seat = {
            id: `new-${tempIdCounter}`,
            label: uniqueLabel(`A${tempIdCounter}`),
            kind: 'seat',
            type: 'normal',
            deck: currentDeck,
            shape: 'rect',
            width: SEAT_SIZE,
            height: SEAT_SIZE,
            corner_radius: 8,
            border_width: 2,
            ...spawnPosition(SEAT_SIZE, SEAT_SIZE),
        };

        const group = buildSeatGroup(seat);
        layer.add(group);
        layer.draw();
        selectOnly(group);
    } catch (error) {
        reportError('No se pudo agregar el asiento', error);
    }
});

objectAddButton.addEventListener('click', () => {
    try {
        tempIdCounter += 1;
        const kind = objectKindSelect.value;
        const isOutline = kind === 'outline';

        // The outline exists to draw the bus body's silhouette, so it spawns
        // pre-sized to most of the canvas instead of the default 40x40 —
        // a rectangle that small would be useless for that purpose.
        const width = isOutline ? Math.max(200, config.canvasWidth - 60) : SEAT_SIZE;
        const height = isOutline ? Math.max(200, config.canvasHeight - 60) : SEAT_SIZE;

        const seat = {
            id: `new-${tempIdCounter}`,
            label: uniqueLabel(OBJECT_DEFAULT_LABELS[kind] ?? 'OBJETO'),
            kind: 'object',
            type: kind,
            deck: currentDeck,
            shape: 'rect',
            width,
            height,
            corner_radius: isOutline ? 40 : 8,
            border_width: isOutline ? 4 : 2,
            color: isOutline ? outlineColorInput.value : null,
            ...spawnPosition(width, height),
        };

        const group = buildSeatGroup(seat);
        layer.add(group);
        sendBackgroundObjectsToBack();
        layer.draw();
        selectOnly(group);
        setStatus(`Objeto "${seat.label}" agregado. Está seleccionado y resaltado en el lienzo — arrástralo a su lugar.`, 'text-emerald-600');
    } catch (error) {
        reportError('No se pudo agregar el objeto', error);
    }
});

outlineColorInput.addEventListener('input', () => {
    if (selectedGroups.size !== 1) return;
    const [only] = selectedGroups;
    if (only.getAttr('seatKind') !== 'object' || only.getAttr('seatType') !== 'outline') return;

    only.setAttr('seatColor', outlineColorInput.value);
    renderShapeVisuals(only);
    layer.batchDraw();
});

/* ---------- Grid generator ---------- */

gridGenerateButton.addEventListener('click', () => {
    try {
        const rows = Math.min(20, Math.max(1, parseInt(gridRowsInput.value, 10) || 1));
        const cols = Math.min(10, Math.max(1, parseInt(gridColsInput.value, 10) || 1));
        const prefix = gridPrefixInput.value.trim();

        for (let row = 0; row < rows; row++) {
            for (let col = 0; col < cols; col++) {
                const columnLetter = String.fromCharCode(65 + (col % 26));
                const label = uniqueLabel(`${prefix}${row + 1}${columnLetter}`);

                const seat = {
                    id: null,
                    label,
                    kind: 'seat',
                    type: 'normal',
                    deck: currentDeck,
                    shape: 'rect',
                    width: SEAT_SIZE,
                    height: SEAT_SIZE,
                    corner_radius: 8,
                    border_width: 2,
                    pos_x: GRID_ORIGIN.x + col * (SEAT_SIZE + GRID_GAP),
                    pos_y: GRID_ORIGIN.y + row * (SEAT_SIZE + GRID_GAP),
                };

                layer.add(buildSeatGroup(seat));
            }
        }

        layer.draw();
        setStatus(`Se generaron ${rows * cols} asientos. Ajusta su posición y guarda cuando termines.`);
    } catch (error) {
        reportError('No se pudo generar la grilla', error);
    }
});

/* ---------- Save ---------- */

saveButton.addEventListener('click', async () => {
    const seats = allSeatGroups().map((group) => ({
        id: typeof group.getAttr('seatId') === 'number' ? group.getAttr('seatId') : null,
        label: group.getAttr('seatLabel'),
        kind: group.getAttr('seatKind'),
        type: group.getAttr('seatType'),
        deck: group.getAttr('seatDeck'),
        shape: group.getAttr('seatShape'),
        width: group.getAttr('seatWidth'),
        height: group.getAttr('seatHeight'),
        corner_radius: group.getAttr('cornerRadius'),
        border_width: group.getAttr('borderWidth'),
        color: group.getAttr('seatColor'),
        allowed_trip_type: group.getAttr('allowedTripType') ?? 'both',
        pos_x: group.x(),
        pos_y: group.y(),
    }));

    setStatus('Guardando...');

    try {
        const response = await axios.put(config.syncUrl, { seats });
        const groups = allSeatGroups();
        const savedByKey = new Map(
            response.data.seats.map((seat) => [`${seat.deck}:${seat.label}`, seat])
        );

        groups.forEach((group) => {
            const key = `${group.getAttr('seatDeck')}:${group.getAttr('seatLabel')}`;
            const saved = savedByKey.get(key);
            if (saved) {
                group.setAttr('seatId', saved.id);
            }
        });

        setStatus('Distribución guardada correctamente.', 'text-emerald-600');
    } catch (error) {
        setStatus(error.response?.data?.message ?? 'No se pudo guardar la distribución.', 'text-red-600');
    }
});

/* ---------- Template export / import ----------
 * Lets the admin save the current shape (canvas dimensions) + seat
 * arrangement as a portable JSON file, then load that same file into
 * another unit on another environment to recreate the layout without
 * rebuilding it seat-by-seat. Everything is local to the client — no
 * server roundtrip — and the user still has to click "Guardar
 * distribución" to persist the imported layout to the database.
 */

const templateExportButton = document.getElementById('template-export');
const templateImportButton = document.getElementById('template-import-button');
const templateImportInput = document.getElementById('template-import-input');

const TEMPLATE_VERSION = 1;
// Fields we round-trip to/from the JSON. IDs and bus_unit_id are
// deliberately excluded — they're environment-specific and the import
// path always treats incoming seats as fresh inserts.
const TEMPLATE_SEAT_KEYS = [
    'label', 'kind', 'type', 'deck', 'shape',
    'width', 'height', 'corner_radius', 'border_width',
    'color', 'allowed_trip_type', 'pos_x', 'pos_y',
];

function buildTemplateFromCurrent() {
    return {
        version: TEMPLATE_VERSION,
        exported_at: new Date().toISOString(),
        template_name: config.unitName ?? 'plantilla',
        canvas_width: config.canvasWidth,
        canvas_height: config.canvasHeight,
        seats: allSeatGroups().map((group) => {
            const seat = {};
            TEMPLATE_SEAT_KEYS.forEach((key) => {
                if (key === 'corner_radius') seat[key] = group.getAttr('cornerRadius');
                else if (key === 'border_width') seat[key] = group.getAttr('borderWidth');
                else if (key === 'pos_x') seat[key] = group.x();
                else if (key === 'pos_y') seat[key] = group.y();
                else if (key === 'allowed_trip_type') seat[key] = group.getAttr('allowedTripType');
                else seat[key] = group.getAttr(`seat${key.charAt(0).toUpperCase()}${key.slice(1)}`);
            });
            return seat;
        }),
    };
}

function slugify(text) {
    return (text ?? 'plantilla')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40) || 'plantilla';
}

function downloadTemplate() {
    try {
        const template = buildTemplateFromCurrent();
        const json = JSON.stringify(template, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const today = new Date().toISOString().slice(0, 10);
        const filename = `merlo-plantilla-${slugify(template.template_name)}-${today}.json`;

        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        // Give the browser a tick to actually start the download before
        // revoking the object URL, otherwise some browsers abort the fetch.
        setTimeout(() => URL.revokeObjectURL(url), 0);

        setStatus(`Plantilla "${template.template_name}" exportada (${template.seats.length} asientos).`, 'text-emerald-600');
    } catch (error) {
        reportError('No se pudo exportar la plantilla', error);
    }
}

function parseTemplate(raw) {
    const data = JSON.parse(raw);
    if (!data || typeof data !== 'object') {
        throw new Error('El archivo no contiene un objeto JSON válido.');
    }
    if (!Array.isArray(data.seats)) {
        throw new Error('Falta la lista de asientos (seats) en la plantilla.');
    }
    // Cheap shape check on the first seat — better to fail fast here than
    // half-import 30 seats and then error on the 31st one.
    const sample = data.seats[0];
    if (sample && typeof sample !== 'object') {
        throw new Error('Algún asiento de la plantilla no tiene el formato correcto.');
    }
    return data;
}

function applyTemplate(template) {
    // Drop every seat currently on the canvas. Divider + outline groups
    // (the background "silueta" / "vitral" objects) live in the same
    // Group layer, so a blanket destroy of all seat groups is correct
    // here — the template is the new full picture.
    allSeatGroups().forEach((group) => group.destroy());
    selectedGroups.clear();

    template.seats.forEach((seatData) => {
        const seat = { id: null };
        TEMPLATE_SEAT_KEYS.forEach((key) => {
            seat[key] = seatData[key] ?? null;
        });
        // Required fields must have sensible defaults or buildSeatGroup
        // would render the seat off-screen / with no size.
        seat.width = seat.width ?? SEAT_SIZE;
        seat.height = seat.height ?? SEAT_SIZE;
        seat.pos_x = seat.pos_x ?? 0;
        seat.pos_y = seat.pos_y ?? 0;
        seat.shape = seat.shape ?? 'rect';
        seat.kind = seat.kind ?? 'seat';
        seat.deck = seat.deck ?? 'lower';

        const group = buildSeatGroup(seat);
        layer.add(group);
    });

    // Reflect the template's canvas size in the "Datos de la unidad"
    // form so the admin can save it from there. We don't trigger a save
    // here — the import is a local-only operation; the user must hit
    // "Guardar datos" for the dimension change to hit the database.
    const widthInput = document.getElementById('canvas_width');
    const heightInput = document.getElementById('canvas_height');
    if (widthInput && Number.isFinite(template.canvas_width)) {
        widthInput.value = template.canvas_width;
    }
    if (heightInput && Number.isFinite(template.canvas_height)) {
        heightInput.value = template.canvas_height;
    }

    sendBackgroundObjectsToBack();
    layer.draw();
    syncSelectionUI();
}

function handleTemplateImportFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (evt) => {
        try {
            const template = parseTemplate(evt.target.result);
            const seatCount = template.seats.length;
            const dimChanged = Number(template.canvas_width) !== config.canvasWidth
                || Number(template.canvas_height) !== config.canvasHeight;
            const summary = [
                `Plantilla: ${template.template_name ?? 'sin nombre'}`,
                `Asientos: ${seatCount}`,
                `Lienzo: ${template.canvas_width}×${template.canvas_height}` + (dimChanged ? ' (cambia el actual)' : ''),
                '',
                'Esto REEMPLAZARÁ toda la distribución actual. Podrás ajustar y luego pulsar "Guardar distribución".',
            ].join('\n');

            if (!window.confirm(summary)) {
                setStatus('Importación cancelada.');
                return;
            }

            applyTemplate(template);
            const dimNote = dimChanged
                ? ' Las dimensiones del lienzo cambiaron — recuerda pulsar "Guardar datos" en "Datos de la unidad" antes de "Guardar distribución".'
                : ' Ahora pulsa "Guardar distribución" para persistir los cambios.';
            setStatus(`Plantilla importada (${seatCount} asientos).${dimNote}`, 'text-emerald-600');
        } catch (error) {
            reportError('No se pudo importar la plantilla', error);
        } finally {
            // Reset the input so picking the same file again still fires
            // the change event (browsers swallow it otherwise).
            if (templateImportInput) templateImportInput.value = '';
        }
    };
    reader.onerror = () => reportError('No se pudo leer el archivo', reader.error);
    reader.readAsText(file);
}

function openTemplateFilePicker() {
    if (templateImportInput) templateImportInput.click();
}

function onTemplateFileChange(evt) {
    const file = evt.target.files?.[0];
    handleTemplateImportFile(file);
}

if (templateExportButton) templateExportButton.addEventListener('click', downloadTemplate);
if (templateImportButton) templateImportButton.addEventListener('click', openTemplateFilePicker);
if (templateImportInput) templateImportInput.addEventListener('change', onTemplateFileChange);

/* ---------- Dev-server hot-reload cleanup ----------
 * Without this, every time this file is edited while the page stays open,
 * Vite hot-reloads the module IN PLACE: a brand new Konva.Stage gets mounted
 * into the same #seat-canvas div on top of the old one, and a whole new set
 * of document/window-level listeners (mouseup, keydown, resize, ...) gets
 * added without the previous set ever being removed. The old and new copies
 * then run side by side and fight each other — selections that "don't reset",
 * drags that only move one seat, general stutter — without any single bug
 * in the logic above. Tearing everything down here before the next reload
 * takes over guarantees only one clean copy is ever live at a time.
 */
if (import.meta.hot) {
    import.meta.hot.dispose(() => {
        window.removeEventListener('resize', resizeStageToContainer);
        document.removeEventListener('keydown', handleEditorKeydown);
        document.removeEventListener('keydown', handleSpaceKeydown);
        document.removeEventListener('keyup', handleSpaceKeyup);
        document.removeEventListener('mouseup', handleGlobalPointerUp);
        document.removeEventListener('touchend', handleGlobalPointerUp);
        document.removeEventListener('mouseleave', handleGlobalPointerUp);
        window.removeEventListener('blur', handleGlobalPointerUp);
        document.removeEventListener('mousemove', handleGlobalMouseMoveSafetyNet);
        document.removeEventListener('click', handleDocumentClickOutside);
        if (templateExportButton) templateExportButton.removeEventListener('click', downloadTemplate);
        if (templateImportButton) templateImportButton.removeEventListener('click', openTemplateFilePicker);
        if (templateImportInput) templateImportInput.removeEventListener('change', onTemplateFileChange);
        stage.destroy();
    });
}
