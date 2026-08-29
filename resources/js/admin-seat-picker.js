import Konva from 'konva';

const config = window.__ADMIN_SEAT_PICKER__;

const SEAT_SIZE = 40;
const CONTENT_PADDING = 24;

const AVAILABLE_COLORS = { fill: '#FFFFFF', stroke: '#15803D' };
const PENDING_COLORS = { fill: '#FACC15', stroke: '#A16207' };
const SENT_COLORS = { fill: '#3B82F6', stroke: '#1D4ED8' };
const SOLD_COLORS = { fill: '#EF4444', stroke: '#991B1B' };
const DISABLED_COLORS = { fill: '#D1D5DB', stroke: '#6B7280' };
const OBJECT_COLORS = { fill: '#94A3B8', stroke: '#475569' };
const OUTLINE_DEFAULT_COLOR = '#2B1113';
const VIP_ACCENT_STROKE = '#F5B301';
const SELECTED_ACCENT_STROKE = '#16A34A';
const SELECTED_ACCENT_WIDTH = 4;
// "Other-type" seat: bookable in general, but not for the trip type
// the admin currently has selected. Render dimmed so the picker still
// shows the full layout and the admin can flip the toggle to see the
// other set come back to life.
const OTHER_TYPE_COLORS = { fill: '#E5E7EB', stroke: '#9CA3AF' };

const seatStatuses = config.seatStatuses ?? {};
const takenIds = new Set(config.takenIds ?? []);

let currentTripType = 'one_way';

const seatNodesById = new Map();
const selectedIds = new Set();

function isSeatSelectable(seat) {
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (takenIds.has(seat.id)) return false;
    if (seatStatuses[seat.id]) return false; // pending or sent
    // Per-seat allowed-trip-type from the bus-unit editor: a seat flagged
    // 'one_way' is greyed out when the admin is on the round-trip toggle.
    const allowed = seat.allowed_trip_type ?? 'both';
    if (allowed !== 'both' && allowed !== currentTripType) return false;
    return true;
}

function isOtherType(seat) {
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (takenIds.has(seat.id)) return false;
    if (seatStatuses[seat.id]) return false;
    const allowed = seat.allowed_trip_type ?? 'both';
    return allowed !== 'both' && allowed !== currentTripType;
}

function hexToRgba(hex, alpha) {
    const clean = (hex || OUTLINE_DEFAULT_COLOR).replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function colorsFor(seat) {
    if (seat.kind === 'object' && seat.type === 'outline') {
        const stroke = seat.color || OUTLINE_DEFAULT_COLOR;
        return { fill: hexToRgba(stroke, 0.05), stroke };
    }
    if (seat.kind === 'object' && seat.type === 'divider') {
        return { fill: hexToRgba(OBJECT_COLORS.stroke, 0.12), stroke: hexToRgba(OBJECT_COLORS.stroke, 0.5) };
    }
    if (seat.kind === 'object') return OBJECT_COLORS;
    if (seat.type === 'disabled') return DISABLED_COLORS;
    if (takenIds.has(seat.id)) return SOLD_COLORS;
    const status = seatStatuses[seat.id];
    if (status === 'sent') return SENT_COLORS;
    if (status === 'pending') return PENDING_COLORS;
    if (isOtherType(seat)) return OTHER_TYPE_COLORS;
    return AVAILABLE_COLORS;
}

function isVip(seat) {
    return seat.kind === 'seat' && seat.type === 'vip';
}

function computeContentBounds(seats) {
    if (seats.length === 0) {
        return { minX: 0, minY: 0, width: config.canvasWidth, height: config.canvasHeight };
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

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
const contentOffset = {
    x: -contentBounds.minX + CONTENT_PADDING,
    y: -contentBounds.minY + CONTENT_PADDING,
};
const naturalWidth = contentBounds.width + CONTENT_PADDING * 2;
const naturalHeight = contentBounds.height + CONTENT_PADDING * 2;

const stage = new Konva.Stage({
    container: 'admin-seat-canvas',
    width: naturalWidth,
    height: naturalHeight,
});
const layer = new Konva.Layer(contentOffset);
stage.add(layer);

function fitStageToContainer() {
    const container = document.getElementById('admin-seat-canvas');
    if (!container) return;
    const availableWidth = container.clientWidth;
    const scale = availableWidth > 0 && availableWidth < naturalWidth ? availableWidth / naturalWidth : 1;
    stage.scale({ x: scale, y: scale });
    stage.width(naturalWidth * scale);
    stage.height(naturalHeight * scale);
    stage.batchDraw();
}

window.addEventListener('resize', fitStageToContainer);

function buildSeatNode(seat) {
    const width = seat.width ?? SEAT_SIZE;
    const height = seat.height ?? SEAT_SIZE;
    const group = new Konva.Group({
        x: seat.pos_x,
        y: seat.pos_y,
        listening: isSeatSelectable(seat),
    });

    const colors = colorsFor(seat);
    const wantsEllipse = seat.shape === 'circle';

    const rect = wantsEllipse
        ? new Konva.Ellipse({
            x: width / 2,
            y: height / 2,
            radiusX: width / 2,
            radiusY: height / 2,
            fill: colors.fill,
            stroke: colors.stroke,
            strokeWidth: isVip(seat) ? Math.max(seat.border_width ?? 2, 3) : (seat.border_width ?? 2),
        })
        : new Konva.Rect({
            x: 0,
            y: 0,
            width,
            height,
            fill: colors.fill,
            stroke: colors.stroke,
            strokeWidth: seat.border_width ?? 2,
            cornerRadius: seat.corner_radius ?? 6,
        });

    group.add(rect);

    // VIP gold accent ring around the base seat color so "is VIP" stays
    // readable even when the seat is also "sold" or "pending".
    if (isVip(seat)) {
        group.add(new Konva.Rect({
            x: -2,
            y: -2,
            width: width + 4,
            height: height + 4,
            stroke: VIP_ACCENT_STROKE,
            strokeWidth: 2,
            cornerRadius: (seat.corner_radius ?? 6) + 2,
            listening: false,
        }));
    }

    const text = new Konva.Text({
        x: 0,
        y: 0,
        width,
        height,
        align: 'center',
        verticalAlign: 'middle',
        text: seat.label,
        fontSize: 9,
        fontStyle: 'bold',
        fill: '#2B1113',
        listening: false,
    });
    group.add(text);

    return { group, rect, text };
}

config.seats.forEach((seat) => {
    const node = buildSeatNode(seat);
    node.seat = seat;
    layer.add(node.group);
    seatNodesById.set(seat.id, node);

    if (isSeatSelectable(seat)) {
        node.group.on('click tap', () => toggleSeat(seat));
        node.group.on('mouseenter', () => { stage.container().style.cursor = 'pointer'; });
        node.group.on('mouseleave', () => { stage.container().style.cursor = 'default'; });
    }
});

stage.on('click tap', (e) => {
    // Clicks on empty canvas deselect everything.
    if (e.target === stage) {
        clearSelection();
    }
});

function toggleSeat(seat) {
    if (selectedIds.has(seat.id)) {
        selectedIds.delete(seat.id);
    } else {
        selectedIds.add(seat.id);
    }
    repaintSeat(seat.id);
    updateForm();
}

function clearSelection() {
    if (selectedIds.size === 0) return;
    const ids = Array.from(selectedIds);
    selectedIds.clear();
    ids.forEach(repaintSeat);
    updateForm();
}

function repaintSeat(seatId) {
    const node = seatNodesById.get(seatId);
    if (!node) return;
    const colors = colorsFor(node.seat);
    const isSelected = selectedIds.has(seatId);
    const baseStrokeWidth = node.seat.border_width ?? 2;

    node.rect.fill(colors.fill);
    node.rect.stroke(isSelected ? SELECTED_ACCENT_STROKE : colors.stroke);
    node.rect.strokeWidth(isSelected ? SELECTED_ACCENT_WIDTH : baseStrokeWidth);
    layer.batchDraw();
}

function updateForm() {
    const summaryEl = document.getElementById('apartado-selected-summary');
    const inputsEl = document.getElementById('apartado-hidden-inputs');
    const submitBtn = document.getElementById('apartado-submit');
    if (!summaryEl || !inputsEl || !submitBtn) return;

    // Rebuild the hidden inputs from scratch — simpler than diffing, and
    // the selection set stays small (typically a handful of seats).
    inputsEl.replaceChildren();

    const labels = Array.from(selectedIds)
        .map((id) => seatNodesById.get(id)?.seat.label)
        .filter(Boolean)
        .sort();

    selectedIds.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'seat_ids[]';
        input.value = id;
        inputsEl.appendChild(input);
    });

    if (labels.length === 0) {
        summaryEl.className = 'mt-4 rounded-xl bg-[#FFFBF6] p-3 text-xs text-[#2B1113]/60';
        summaryEl.textContent = 'Clic en el plano para seleccionar asientos.';
    } else {
        summaryEl.className = 'mt-4 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800 ring-1 ring-emerald-200';
        summaryEl.innerHTML = `<strong>${labels.length}</strong> asiento${labels.length === 1 ? '' : 's'} seleccionado${labels.length === 1 ? '' : 's'}: ${labels.map((l) => `<span class="inline-block rounded bg-white px-1.5 py-0.5 font-bold mr-1 ring-1 ring-emerald-200">${l}</span>`).join('')}`;
    }

    submitBtn.disabled = selectedIds.size === 0;
}

// Trip type toggle on the apartado form: updates the hidden input,
// repaints every seat so non-matching ones fade to the dimmed color,
// drops any selection that no longer matches the new type, and
// refreshes the submit-enabled state.
function setAdminTripType(type) {
    if (type !== 'one_way' && type !== 'round_trip') return;
    currentTripType = type;

    const input = document.getElementById('admin-trip-type-input');
    if (input) input.value = type;

    const tabs = document.querySelectorAll('[data-trip-type]');
    tabs.forEach((tab) => {
        const isActive = tab.getAttribute('data-trip-type') === type;
        tab.classList.toggle('bg-[#8C1D2B]', isActive);
        tab.classList.toggle('text-white', isActive);
        tab.classList.toggle('text-[#2B1113]/60', !isActive);
    });

    // Drop any selection that's no longer valid for the new type so the
    // form never submits seats that the server would reject.
    const stale = Array.from(selectedIds).filter((id) => {
        const node = seatNodesById.get(id);
        if (!node) return false;
        return ! isSeatSelectable(node.seat);
    });
    stale.forEach((id) => selectedIds.delete(id));

    config.seats.forEach((seat) => {
        const node = seatNodesById.get(seat.id);
        if (!node) return;
        node.group.listening(isSeatSelectable(seat));
        // Re-run repaint so colors reflect the new state.
        repaintSeat(seat.id);
    });
    layer.batchDraw();
    updateForm();
}

document.querySelectorAll('[data-trip-type]').forEach((tab) => {
    tab.addEventListener('click', () => setAdminTripType(tab.getAttribute('data-trip-type')));
});

fitStageToContainer();
setAdminTripType('one_way');
