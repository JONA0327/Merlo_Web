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

// 'one_way' and 'especial' can pick ANY bookable seat — no
// allowed_trip_type / zone restriction applies to them. The zone chips
// are still there as a quick-select convenience for 'especial'
// (mancuernas), they just no longer gate which seats can be clicked
// directly. 'regreso' is eligibility-equivalent to 'one_way' before this
// change, but now it still respects allowed_trip_type (only 'round_trip'
// is actually restricted in practice).
function matchesTripType(seat, type) {
    if (type === 'one_way' || type === 'especial') return true;
    const allowed = seat.allowed_trip_type ?? 'both';
    const effectiveType = type === 'regreso' ? 'one_way' : type;
    return allowed === 'both' || allowed === effectiveType;
}

function isSeatSelectable(seat) {
    if (config.tripEnded) return false;
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (takenIds.has(seat.id)) return false;
    if (seatStatuses[seat.id]) return false; // pending or sent
    return matchesTripType(seat, currentTripType);
}

function isOtherType(seat) {
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (takenIds.has(seat.id)) return false;
    if (seatStatuses[seat.id]) return false;
    return ! matchesTripType(seat, currentTripType);
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
    // "Especial" is sold as a whole mancuerna, not seat-by-seat — clicking
    // any one seat of a pair selects (or deselects) both together. A
    // tagged zone (set in the bus editor) wins when present; otherwise
    // fall back to whichever bookable seat sits right next to it in the
    // same row, on the same side of the aisle — works out of the box
    // without requiring the admin to tag every pair first.
    if (currentTripType === 'especial') {
        if (seat.zone) {
            toggleZone(seat.zone);
            return;
        }
        const partner = findAdjacentPartner(seat);
        if (partner) {
            togglePair(seat, partner);
            return;
        }
    }

    if (selectedIds.has(seat.id)) {
        selectedIds.delete(seat.id);
    } else {
        selectedIds.add(seat.id);
    }
    repaintSeat(seat.id);
    updateForm();
}

// Toggles every selectable seat sharing this zone together: if the whole
// group is already selected, clicking any of them clears all of them;
// otherwise it selects whichever aren't already selected.
function toggleZone(zoneName) {
    const zoneSeats = config.seats.filter((s) => s.zone === zoneName && isSeatSelectable(s));
    if (zoneSeats.length === 0) return;

    const allSelected = zoneSeats.every((s) => selectedIds.has(s.id));
    zoneSeats.forEach((s) => {
        if (allSelected) {
            selectedIds.delete(s.id);
        } else {
            selectedIds.add(s.id);
        }
        repaintSeat(s.id);
    });
    updateForm();
}

// Same toggle-together behavior as toggleZone, but for an ad-hoc pair of
// seats (no shared zone tag) — only the ones still actually selectable
// get toggled, so clicking a pair where the partner is already taken
// still selects the one seat that's free.
function togglePair(seatA, seatB) {
    const pair = [seatA, seatB].filter((s) => isSeatSelectable(s));
    if (pair.length === 0) return;

    const allSelected = pair.every((s) => selectedIds.has(s.id));
    pair.forEach((s) => {
        if (allSelected) {
            selectedIds.delete(s.id);
        } else {
            selectedIds.add(s.id);
        }
        repaintSeat(s.id);
    });
    updateForm();
}

// Finds the nearest OTHER bookable seat in the same row (same deck,
// roughly the same pos_y) that sits immediately beside this one — the
// gap has to be small enough that it isn't across the aisle, which is
// always noticeably wider than the gap between two seats sharing a row.
function findAdjacentPartner(seat) {
    if (seat.kind !== 'seat') return null;

    const rowTolerance = (seat.height ?? SEAT_SIZE) * 0.6;
    const candidates = config.seats.filter((s) =>
        s.id !== seat.id
        && s.kind === 'seat'
        && s.type !== 'disabled'
        && s.deck === seat.deck
        && Math.abs((s.pos_y ?? 0) - (seat.pos_y ?? 0)) < rowTolerance
    );
    if (candidates.length === 0) return null;

    candidates.sort((a, b) => Math.abs(a.pos_x - seat.pos_x) - Math.abs(b.pos_x - seat.pos_x));
    const nearest = candidates[0];
    const gap = Math.abs(nearest.pos_x - seat.pos_x);
    const maxPairGap = (seat.width ?? SEAT_SIZE) * 1.8;

    return gap <= maxPairGap ? nearest : null;
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

// Trip type select on the apartado form: repaints every seat so
// non-matching ones fade to the dimmed color, drops any selection that no
// longer matches the new type, toggles the zone picker panel, and
// refreshes the submit-enabled state.
function setAdminTripType(type) {
    currentTripType = type;

    const zonePicker = document.getElementById('admin-zone-picker');
    if (zonePicker) zonePicker.classList.toggle('hidden', type !== 'especial' && type !== 'regreso');

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

// Adds every seat in the given zone to the current selection (without
// clearing what's already picked) — lets the admin click a "Mancuerna 1"
// chip instead of clicking each seat individually.
function selectZone(zoneName) {
    config.seats.forEach((seat) => {
        if (seat.zone !== zoneName) return;
        if (!isSeatSelectable(seat)) return;
        selectedIds.add(seat.id);
        repaintSeat(seat.id);
    });
    updateForm();
}

const tripTypeSelect = document.getElementById('admin-trip-type-select');
if (tripTypeSelect) {
    tripTypeSelect.addEventListener('change', () => setAdminTripType(tripTypeSelect.value));
}

document.querySelectorAll('.admin-zone-chip').forEach((chip) => {
    chip.addEventListener('click', () => selectZone(chip.getAttribute('data-zone')));
});

fitStageToContainer();
setAdminTripType(tripTypeSelect ? tripTypeSelect.value : 'one_way');
