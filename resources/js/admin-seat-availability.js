import Konva from 'konva';

const config = window.__ADMIN_SEAT_AVAILABILITY__;

const SEAT_SIZE = 40;
const CONTENT_PADDING = 24;

// Color per allowed_trip_type. The same palette the toolbar buttons
// use, so the user can always tell "if I click now, the seat will
// turn into this color".
const MODE_COLORS = {
    both: { fill: '#FFFFFF', stroke: '#15803D' },
    one_way: { fill: '#3B82F6', stroke: '#1D4ED8' },
    round_trip: { fill: '#FACC15', stroke: '#A16207' },
};
const OBJECT_COLORS = { fill: '#94A3B8', stroke: '#475569' };
const NON_SEAT_COLORS = { fill: '#CBD5E1', stroke: '#94A3B8' };
const OUTLINE_DEFAULT_COLOR = '#2B1113';
const DIRTY_HALO = '#16A34A';

let currentMode = 'one_way';
const dirty = new Map(); // seat_id -> { from: 'both', to: 'one_way' }
const seatNodesById = new Map();
const originalMode = new Map(); // snapshot at load time, for the "X changed" stat

function hexToRgba(hex, alpha) {
    const clean = (hex || OUTLINE_DEFAULT_COLOR).replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function isPaintable(seat) {
    // Only actual seats (kind='seat') can have a per-trip-type rule.
    // Doors, outlines, dividers, etc. show as grey "reference" but
    // are not clickable.
    return seat.kind === 'seat';
}

function currentModeFor(seat) {
    // Dirty override beats whatever's saved in the DB so the live color
    // tracks what the user just painted.
    if (dirty.has(seat.id)) return dirty.get(seat.id).to;
    return seat.allowed_trip_type ?? 'both';
}

// Mirrors the editor's colorsFor() so the same bus unit looks
// identical in both views.
//
// The outline and dividers are huge shapes that span most of the
// bus — if their fill were opaque they'd cover every seat drawn on
// top of them. We use a slightly higher alpha than the editor (0.10
// instead of 0.05) because the availability view is zoomed-out
// more often and a near-zero fill is invisible there anyway.
//
// Regular reference objects (door, stairs, bathroom, driver, …)
// get the same semi-transparent treatment so they read as
// "background plumbing" instead of opaque grey blocks competing
// with the seats for attention. The label text on top still names
// each one so the admin can tell doors from stairs.
function colorsFor(seat) {
    if (seat.kind === 'object' && seat.type === 'outline') {
        const stroke = seat.color || OUTLINE_DEFAULT_COLOR;
        return { fill: hexToRgba(stroke, 0.10), stroke };
    }
    if (seat.kind === 'object' && seat.type === 'divider') {
        return { fill: hexToRgba(OBJECT_COLORS.stroke, 0.10), stroke: hexToRgba(OBJECT_COLORS.stroke, 0.5) };
    }
    if (seat.kind === 'object') {
        // Light enough that the seats behind are still visible, dark
        // enough that you can see the object outline clearly.
        return { fill: hexToRgba(OBJECT_COLORS.stroke, 0.10), stroke: OBJECT_COLORS.stroke };
    }
    return MODE_COLORS[currentModeFor(seat)] ?? MODE_COLORS.both;
}

function computeContentBounds(seats) {
    if (seats.length === 0) {
        return { minX: 0, minY: 0, width: config.canvasWidth, height: config.canvasHeight };
    }
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    seats.forEach((seat) => {
        const width = seat.width ?? SEAT_SIZE;
        const height = seat.height ?? SEAT_SIZE;
        minX = Math.min(minX, seat.pos_x);
        minY = Math.min(minY, seat.pos_y);
        maxX = Math.max(maxX, seat.pos_x + width);
        maxY = Math.max(maxY, seat.pos_y + height);
    });
    return { minX, minY, width: maxX - minX, height: maxY - minY };
}

const contentBounds = computeContentBounds(config.seats);
const contentOffset = { x: -contentBounds.minX + CONTENT_PADDING, y: -contentBounds.minY + CONTENT_PADDING };
const naturalWidth = contentBounds.width + CONTENT_PADDING * 2;
const naturalHeight = contentBounds.height + CONTENT_PADDING * 2;

const stage = new Konva.Stage({ container: 'availability-canvas', width: naturalWidth, height: naturalHeight });
const layer = new Konva.Layer(contentOffset);
stage.add(layer);

function fitStageToContainer() {
    const container = document.getElementById('availability-canvas');
    if (!container) return;
    const availableWidth = container.clientWidth;
    // Only ever scale down — never enlarge past the natural canvas
    // size, so the seats don't get blurry on a wide monitor.
    const scale = availableWidth > 0 && availableWidth < naturalWidth ? availableWidth / naturalWidth : 1;
    stage.scale({ x: scale, y: scale });
    stage.width(naturalWidth * scale);
    stage.height(naturalHeight * scale);
    stage.batchDraw();
    // Force the Konva layer to redraw too — without this, some browsers
    // paint the stage's empty backing canvas first and only show the
    // children after a manual draw.
    layer.draw();
}

window.addEventListener('resize', fitStageToContainer);
// The admin layout paints the sidebar/header first and the canvas
// container measures its width a frame later, so re-fit once after
// the browser has had a chance to lay everything out.
requestAnimationFrame(fitStageToContainer);
setTimeout(fitStageToContainer, 50);

function buildSeatNode(seat) {
    const width = seat.width ?? SEAT_SIZE;
    const height = seat.height ?? SEAT_SIZE;
    const group = new Konva.Group({ x: seat.pos_x, y: seat.pos_y, listening: isPaintable(seat) });

    // Use the same colorsFor() the picker uses — that function knows
    // about outlines (pastel fill so they don't hide seats), dividers,
    // disabled seats, and the per-seat allowed-trip-type paint. Going
    // straight to MODE_COLORS[mode] here would render the outline /
    // divider objects as opaque white-with-green-border, swallowing
    // every seat on top of them.
    const colors = colorsFor(seat);
    const baseBorder = seat.border_width ?? 2;

    const wantsEllipse = seat.shape === 'circle';
    const rect = wantsEllipse
        ? new Konva.Ellipse({
            x: width / 2, y: height / 2,
            radiusX: width / 2, radiusY: height / 2,
            fill: colors.fill, stroke: colors.stroke, strokeWidth: baseBorder,
        })
        : new Konva.Rect({
            x: 0, y: 0, width, height,
            cornerRadius: seat.corner_radius ?? 6,
            fill: colors.fill, stroke: colors.stroke, strokeWidth: baseBorder,
        });

    group.add(rect);

    // Every node gets a label — seats show "A1A", reference objects
    // show "PUERTA" / "ESCALERAS" / "BAÑO" / etc. The picker skips
    // text on non-paintable nodes which leaves them as anonymous
    // grey boxes; the availability view is more useful when the
    // admin can see "oh, that's the bathroom" at a glance.
    const text = new Konva.Text({
        x: 0, y: 0, width, height,
        align: 'center', verticalAlign: 'middle',
        text: seat.label,
        fontSize: Math.min(11, Math.max(7, Math.min(width, height) / 5)),
        fontStyle: 'bold',
        fill: '#2B1113',
        listening: false,
        wrap: 'word',
        ellipsis: true,
    });
    group.add(text);

    return { group, rect, text, seat };
}

config.seats.forEach((seat) => {
    const node = buildSeatNode(seat);
    originalMode.set(seat.id, seat.allowed_trip_type ?? 'both');
    layer.add(node.group);
    seatNodesById.set(seat.id, node);

    if (isPaintable(seat)) {
        node.group.on('click tap', () => paintSeat(seat));
        node.group.on('mouseenter', () => { stage.container().style.cursor = 'pointer'; });
        node.group.on('mouseleave', () => { stage.container().style.cursor = 'default'; });
    }
});

// Shift+drag paint: keep the same mode active while sweeping the
// cursor over a bunch of seats. We track the pressed state on the
// stage so a "tap tap" sequence just paints each individually.
let isShiftDown = false;
let isDragging = false;
window.addEventListener('keydown', (e) => { if (e.key === 'Shift') isShiftDown = true; });
window.addEventListener('keyup', (e) => { if (e.key === 'Shift') isShiftDown = false; });

stage.on('mousedown touchstart', (e) => {
    if (isShiftDown) isDragging = true;
});
stage.on('mouseup touchend mouseleave', () => { isDragging = false; });
stage.on('mousemove touchmove', (e) => {
    if (! isShiftDown || ! isDragging) return;
    const target = e.target;
    const group = target?.findAncestor('Group', true);
    if (! group) return;
    const seatId = group.getAttr('seatDataId');
    if (! seatId) return;
    const node = seatNodesById.get(seatId);
    if (! node || ! isPaintable(node.seat)) return;
    paintSeat(node.seat, /* silent */ true);
});

function paintSeat(seat, silent = false) {
    if (! isPaintable(seat)) return;
    const from = currentModeFor(seat);
    if (from === currentMode) return; // no-op
    dirty.set(seat.id, { from, to: currentMode });
    repaintSeat(seat);
    refreshChangesPanel();
    refreshStats();
    if (! silent) {
        // Light feedback that something happened, without an alert.
    }
}

function repaintSeat(seat) {
    const node = seatNodesById.get(seat.id);
    if (! node) return;
    const colors = colorsFor(seat);
    const isDirty = dirty.has(seat.id);
    const baseBorder = seat.border_width ?? 2;
    node.rect.fill(colors.fill);
    node.rect.stroke(isDirty ? DIRTY_HALO : colors.stroke);
    node.rect.strokeWidth(isDirty ? Math.max(3, baseBorder + 1) : baseBorder);
    layer.batchDraw();
}

function effectiveModeFor(seat) {
    if (dirty.has(seat.id)) return dirty.get(seat.id).to;
    return originalMode.get(seat.id) ?? 'both';
}

function refreshStats() {
    // Effective counts (DB + dirty), per mode. This is what the
    // counters in the toolbar should show so the admin knows what
    // they're about to save before they hit the button.
    const stats = { both: 0, one_way: 0, round_trip: 0 };
    config.seats.forEach((seat) => {
        if (! isPaintable(seat)) return;
        stats[effectiveModeFor(seat)] += 1;
    });
    document.getElementById('stat-both').textContent = stats.both;
    document.getElementById('stat-one-way').textContent = stats.one_way;
    document.getElementById('stat-round-trip').textContent = stats.round_trip;
}

function refreshChangesPanel() {
    const list = document.getElementById('changes-list');
    const empty = document.getElementById('changes-empty');
    const inputs = document.getElementById('changes-inputs');
    const submit = document.getElementById('availability-submit');

    list.replaceChildren();
    inputs.replaceChildren();

    if (dirty.size === 0) {
        empty.classList.remove('hidden');
        submit.disabled = true;
        return;
    }

    empty.classList.add('hidden');
    submit.disabled = false;

    // Group by transition direction so the panel reads "5 seats went
    // from ambos → solo ida, 2 went from solo ida → ambos" — way more
    // useful for a sanity check before saving than a flat list of IDs.
    const groups = new Map();
    for (const [seatId, change] of dirty) {
        const key = `${change.from}->${change.to}`;
        if (! groups.has(key)) groups.set(key, []);
        groups.get(key).push(seatId);
    }

    const labelFor = { both: 'Ambos', one_way: 'Solo ida', round_trip: 'Redondo' };

    for (const [key, seatIds] of groups) {
        const [from, to] = key.split('->');
        const node = document.createElement('li');
        node.className = 'flex items-start justify-between gap-2 rounded-lg bg-[#FFFBF6] px-2.5 py-1.5';
        const labels = seatIds
            .map((id) => seatNodesById.get(id)?.seat.label)
            .filter(Boolean)
            .sort();
        node.innerHTML = `<div>
            <p class="text-[11px] font-semibold text-[#2B1113]">${labelFor[from]} → ${labelFor[to]}</p>
            <p class="text-[10px] text-[#2B1113]/60">${labels.join(', ')}</p>
        </div>
        <button type="button" class="text-[10px] font-semibold text-[#8C1D2B] hover:text-[#6F1622]" data-undo-group="${from}->${to}">Deshacer</button>`;
        list.appendChild(node);
    }

    // Build the form payload — only the dirty seats go on the wire.
    for (const [seatId, change] of dirty) {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = `changes[${seatId}][id]`;
        idInput.value = seatId;
        inputs.appendChild(idInput);

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = `changes[${seatId}][allowed_trip_type]`;
        typeInput.value = change.to;
        inputs.appendChild(typeInput);
    }

    list.querySelectorAll('[data-undo-group]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const [from, to] = btn.getAttribute('data-undo-group').split('->');
            for (const [seatId, change] of Array.from(dirty.entries())) {
                if (change.from === from && change.to === to) {
                    dirty.delete(seatId);
                    repaintSeat(seatNodesById.get(seatId).seat);
                }
            }
            refreshChangesPanel();
            refreshStats();
        });
    });
}

document.querySelectorAll('[data-paint-mode]').forEach((btn) => {
    btn.addEventListener('click', () => setPaintMode(btn.getAttribute('data-paint-mode')));
});

function setPaintMode(mode) {
    if (! ['one_way', 'round_trip', 'both'].includes(mode)) return;
    currentMode = mode;

    const activeClasses = {
        one_way: ['bg-[#3B82F6]', 'text-white', 'border-[#3B82F6]'],
        round_trip: ['bg-[#F5B301]', 'text-[#2B1113]', 'border-[#F5B301]'],
        both: ['bg-[#15803D]', 'text-white', 'border-[#15803D]'],
    };
    const inactiveClasses = ['bg-white', 'text-[#2B1113]'];

    document.querySelectorAll('[data-paint-mode]').forEach((el) => {
        el.classList.remove('bg-[#3B82F6]', 'text-white', 'border-[#3B82F6]', 'bg-[#F5B301]', 'bg-[#15803D]', 'bg-white');
        el.classList.add('border-2');
        if (el.getAttribute('data-paint-mode') === mode) {
            el.classList.add(...activeClasses[mode]);
        } else {
            el.classList.add(...inactiveClasses);
        }
    });
}

// Tag the seat groups with their DB id so the shift-drag handler can
// find the right one without walking the tree.
config.seats.forEach((seat) => {
    const node = seatNodesById.get(seat.id);
    if (node) node.group.setAttr('seatDataId', seat.id);
});

fitStageToContainer();
setPaintMode('one_way');
refreshStats();

// Force an explicit redraw after every node is added — without this
// the very first paint (right after the loop above) can end up empty
// in some browsers if the container's width was 0 when the stage
// sized itself. Cheap and idempotent.
layer.draw();
stage.batchDraw();
