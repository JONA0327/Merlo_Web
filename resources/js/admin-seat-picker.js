import Konva from 'konva';

const config = window.__ADMIN_SEAT_PICKER__;

const SEAT_SIZE = 40;
const CONTENT_PADDING = 24;

const AVAILABLE_COLORS = { fill: '#FFFFFF', stroke: '#15803D' };
const PENDING_COLORS = { fill: '#FACC15', stroke: '#A16207' };
const SENT_COLORS = { fill: '#3B82F6', stroke: '#1D4ED8' };
const SOLD_COLORS = { fill: '#EF4444', stroke: '#991B1B' };
const STANDING_COLORS = { fill: '#A855F7', stroke: '#6B21A8' };
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
const seatTripTypes = config.seatTripTypes ?? {};
// Guías only: a pending reservation staged here that's actually a
// return leg of an outbound ticket (SeatReservation::createReturnLegTicket()),
// painted red regardless of the currently-selected category — unlike
// the Apartar asientos "regreso" marking, this isn't gated by category
// since the guide page isn't choosing between trip types the same way.
const seatIsReturnLeg = config.seatIsReturnLeg ?? {};
// Seats with an active StandingReservation ("de planta") for this
// route/bus — booked automatically on every matching trip from now on.
// Only painted while the seat is otherwise free; once it's actually
// taken, the normal pending/sent/sold colors (reflecting what really
// happened) take over instead.
const seatIsStanding = config.seatIsStanding ?? {};
const takenIds = new Set(config.takenIds ?? []);
// Seats whose round-trip/especial passenger's return was released for
// same-day resale (they're not coming back this day) — available, but
// only when booking a "regreso" (return-only) apartado on this seat.
const releasedSeatIds = new Set(config.releasedSeatIds ?? []);
// Seats sold "solo ida" with no return-leg ticket yet — the bus comes
// back with them empty, so they're open for a "regreso" apartado too.
const idaOnlySeatIds = new Set(config.idaOnlySeatIds ?? []);

let currentTripType = 'one_way';

const seatNodesById = new Map();
const selectedIds = new Set();
// Per-seat payment method override — lets a 2+ seat apartado split
// across methods (e.g. one seat cash, one transfer) instead of forcing
// the same method on every seat. Seeded from the "Método de pago"
// default select whenever a seat is added; untouched on re-renders so
// a manual per-seat change survives selecting/deselecting other seats.
const seatPaymentMethods = new Map();
// Per-seat "ya sé cuándo regresa" date — optional, only offered for
// ida/redondo/especial (not for a "regreso" apartado itself). Empty by
// default; only seats where the admin actually typed a date submit one.
const seatReturnDates = new Map();
// Only meaningful alongside a return date for "ida" (a brand-new
// charged sale) — redondo/especial returns are always free/already
// paid regardless, so this never renders for those categories.
const seatReturnPaid = new Map();

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

// "Regreso" on a different day than this trip's own return: it gets
// staged in the guide for that date (the server checks seats there), so
// what's taken on THIS trip doesn't matter for it.
const regresoDateInput = document.querySelector('#admin-regreso-date input[name="regreso_date"]');

function isRegresoOtherDay() {
    return currentTripType === 'regreso'
        && !! regresoDateInput?.value
        && regresoDateInput.value !== (config.tripReturnDate ?? '');
}

function isReleasedForRegreso(seat) {
    if (currentTripType !== 'regreso') return false;
    return isRegresoOtherDay() || releasedSeatIds.has(seat.id) || idaOnlySeatIds.has(seat.id);
}

function isSeatSelectable(seat) {
    if (config.tripEnded) return false;
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (takenIds.has(seat.id) && ! isReleasedForRegreso(seat)) return false;
    if (seatStatuses[seat.id] && ! isReleasedForRegreso(seat)) return false; // pending or sent
    // A "de planta" seat books itself automatically (StandingReservation::
    // applyToTrip()) — never pick it by hand here, that's how two
    // different apartados would end up fighting over the same seat. If
    // the regular passenger isn't coming, release it from the apartado
    // list below first ("No viaja hoy"); it becomes pickable right after.
    if (seatIsStanding[seat.id] && ! isRegresoOtherDay()) return false;
    return matchesTripType(seat, currentTripType);
}

function isOtherType(seat) {
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (takenIds.has(seat.id) && ! isReleasedForRegreso(seat)) return false;
    if (seatStatuses[seat.id] && ! isReleasedForRegreso(seat)) return false;
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
    if (takenIds.has(seat.id) && ! isReleasedForRegreso(seat)) return SOLD_COLORS;
    const status = seatStatuses[seat.id];
    if (status && seatIsReturnLeg[seat.id]) return SOLD_COLORS;
    if (status && isReleasedForRegreso(seat)) return AVAILABLE_COLORS;
    // A seat already claimed by a "regreso" apartado reads red, same as
    // the printed manifest's color for that category — but only while
    // viewing the "regreso" category itself. Whether the return leg is
    // taken is irrelevant when apartando ida/redondo/especial, where the
    // normal pending/sent colors (reflecting the outbound leg) apply.
    if (status && currentTripType === 'regreso' && seatTripTypes[seat.id] === 'regreso') return SOLD_COLORS;
    if (status === 'sent') return SENT_COLORS;
    if (status === 'pending') return PENDING_COLORS;
    if (seatIsStanding[seat.id]) return STANDING_COLORS;
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

    // Always bind — whether a click actually does anything is gated by
    // the node's `listening` flag (toggled per seat in setAdminTripType()
    // via isSeatSelectable()), not by whether a handler exists. A seat
    // that's unselectable under the type shown at page load (e.g. "ida")
    // but becomes selectable after switching type (e.g. "regreso" on a
    // released return seat) still needs its handler already in place.
    if (seat.kind !== 'object' && seat.type !== 'disabled') {
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
    // without requiring the admin to tag every pair first. A mancuerna
    // whose other seat is already taken can't be sold as especial.
    if (currentTripType === 'especial') {
        if (! selectedIds.has(seat.id)) {
            const blocked = blockedPartnersOf(seat);
            if (blocked.length > 0) {
                showMancuernaWarning([seat], blocked);
                return;
            }
        }
        showMancuernaWarning([], []);
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

// The other seat(s) of this seat's mancuerna — every other seat sharing
// its zone tag, or else the adjacent seat beside it in the same row.
function mancuernaPartnersOf(seat) {
    if (seat.zone) {
        return config.seats.filter((s) => s.zone === seat.zone && s.id !== seat.id && s.kind === 'seat' && s.type !== 'disabled');
    }
    const partner = findAdjacentPartner(seat);
    return partner ? [partner] : [];
}

// Partners already held by another passenger (or otherwise not
// pickable) — any of these means the mancuerna can't go as especial.
function blockedPartnersOf(seat) {
    return mancuernaPartnersOf(seat).filter((s) => ! selectedIds.has(s.id) && ! isSeatSelectable(s));
}

// Warning banner above the selection summary. Empty lists hide it.
function showMancuernaWarning(seats, blocked) {
    const summaryEl = document.getElementById('apartado-selected-summary');
    if (!summaryEl) return;
    let warningEl = document.getElementById('apartado-mancuerna-warning');
    if (blocked.length === 0) {
        warningEl?.remove();
        return;
    }
    if (!warningEl) {
        warningEl = document.createElement('div');
        warningEl.id = 'apartado-mancuerna-warning';
        warningEl.setAttribute('role', 'alert');
        warningEl.className = 'mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-900 ring-1 ring-amber-300';
        summaryEl.parentNode.insertBefore(warningEl, summaryEl);
    }
    const labels = (list) => list.map((s) => s.label).join(', ');
    const subject = seats.length > 0 ? `La mancuerna del asiento ${labels(seats)}` : 'Esta mancuerna';
    warningEl.textContent = `⚠️ ${subject} no se puede vender como especial: `
        + `el asiento ${labels(blocked)} ya está en uso por otro pasajero.`;
}

// Switching to "especial" with seats already picked: complete each one's
// mancuerna automatically when the partner is free; when it isn't, drop
// that seat and warn that its mancuerna is already in use.
function completeMancuernas() {
    const rejected = [];
    const blockedAll = [];
    Array.from(selectedIds).forEach((id) => {
        const seat = seatNodesById.get(id)?.seat;
        if (!seat || ! selectedIds.has(id)) return;
        const blocked = blockedPartnersOf(seat);
        if (blocked.length > 0) {
            selectedIds.delete(id);
            rejected.push(seat);
            blocked.forEach((b) => { if (! blockedAll.includes(b)) blockedAll.push(b); });
            return;
        }
        mancuernaPartnersOf(seat).forEach((partner) => selectedIds.add(partner.id));
    });
    showMancuernaWarning(rejected, blockedAll);
}

function clearSelection() {
    if (selectedIds.size === 0) return;
    const ids = Array.from(selectedIds);
    selectedIds.clear();
    showMancuernaWarning([], []);
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

const PAYMENT_METHOD_OPTIONS = [
    ['transfer', 'Transferencia'],
    ['cash', 'Efectivo'],
    ['tbd', 'Por definir'],
];

function updateForm() {
    const summaryEl = document.getElementById('apartado-selected-summary');
    const inputsEl = document.getElementById('apartado-hidden-inputs');
    const submitBtn = document.getElementById('apartado-submit');
    const defaultMethodSelect = document.getElementById('admin-payment-method-select');
    if (!summaryEl || !inputsEl || !submitBtn) return;

    // Drop payment-method entries for seats no longer selected, and seed
    // new ones from the default select so every selected seat always has
    // a value even before the admin touches anything.
    Array.from(seatPaymentMethods.keys()).forEach((id) => {
        if (!selectedIds.has(id)) seatPaymentMethods.delete(id);
    });
    selectedIds.forEach((id) => {
        if (!seatPaymentMethods.has(id)) {
            seatPaymentMethods.set(id, defaultMethodSelect?.value || 'transfer');
        }
    });
    Array.from(seatReturnDates.keys()).forEach((id) => {
        if (!selectedIds.has(id)) seatReturnDates.delete(id);
    });
    Array.from(seatReturnPaid.keys()).forEach((id) => {
        if (!selectedIds.has(id)) seatReturnPaid.delete(id);
    });

    // Rebuild the hidden inputs from scratch — simpler than diffing, and
    // the selection set stays small (typically a handful of seats).
    inputsEl.replaceChildren();

    const seats = Array.from(selectedIds)
        .map((id) => ({ id, label: seatNodesById.get(id)?.seat.label }))
        .filter((s) => s.label)
        .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }));

    selectedIds.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'seat_ids[]';
        input.value = id;
        inputsEl.appendChild(input);
    });

    if (seats.length === 0) {
        summaryEl.className = 'mt-4 rounded-xl bg-[#FFFBF6] p-3 text-xs text-[#2B1113]/60';
        summaryEl.textContent = 'Clic en el plano para seleccionar asientos.';
    } else {
        summaryEl.className = 'mt-4 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800 ring-1 ring-emerald-200';
        const header = `<strong>${seats.length}</strong> asiento${seats.length === 1 ? '' : 's'} seleccionado${seats.length === 1 ? '' : 's'}`
            + (seats.length > 1 ? ' — puedes cambiar el método de pago por asiento:' : ':');
        const showReturnDate = currentTripType !== 'regreso';
        // Only "ida" actually charges for the return leg — for
        // redondo/especial it's always free/already paid, so asking
        // "¿pagado?" there would be meaningless.
        const showReturnPaid = currentTripType === 'one_way';
        const minReturnDate = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        const rows = seats.map((s) => {
            const options = PAYMENT_METHOD_OPTIONS.map(([value, label]) =>
                `<option value="${value}" ${seatPaymentMethods.get(s.id) === value ? 'selected' : ''}>${label}</option>`
            ).join('');
            const returnDateField = showReturnDate
                ? `<input type="date" name="return_date[${s.id}]" data-seat-return="${s.id}" min="${minReturnDate}" value="${seatReturnDates.get(s.id) || ''}" placeholder="Fecha de regreso" title="¿Ya sabe cuándo regresa? (opcional)" class="rounded border border-emerald-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-emerald-800">`
                : '';
            const returnPaidField = showReturnPaid
                ? `<select name="return_paid[${s.id}]" data-seat-return-paid="${s.id}" title="¿El regreso ya está pagado?" class="rounded border border-emerald-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-emerald-800">
                    <option value="0" ${seatReturnPaid.get(s.id) !== '1' ? 'selected' : ''}>Regreso no pagado</option>
                    <option value="1" ${seatReturnPaid.get(s.id) === '1' ? 'selected' : ''}>Regreso pagado</option>
                </select>`
                : '';
            return `<div class="mt-1.5 flex flex-wrap items-center justify-between gap-1.5 rounded-lg bg-white px-2 py-1 ring-1 ring-emerald-200">
                <span class="font-bold">${s.label}</span>
                <select name="payment_method[${s.id}]" data-seat-method="${s.id}" class="rounded border border-emerald-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-emerald-800">${options}</select>
                ${returnDateField}
                ${returnPaidField}
            </div>`;
        }).join('');
        summaryEl.innerHTML = header + rows;

        summaryEl.querySelectorAll('[data-seat-method]').forEach((select) => {
            select.addEventListener('change', () => {
                seatPaymentMethods.set(Number(select.dataset.seatMethod), select.value);
            });
        });
        summaryEl.querySelectorAll('[data-seat-return]').forEach((input) => {
            input.addEventListener('change', () => {
                seatReturnDates.set(Number(input.dataset.seatReturn), input.value);
            });
        });
        summaryEl.querySelectorAll('[data-seat-return-paid]').forEach((select) => {
            select.addEventListener('change', () => {
                seatReturnPaid.set(Number(select.dataset.seatReturnPaid), select.value);
            });
        });
    }

    submitBtn.disabled = selectedIds.size === 0;
}

// Trip type select on the apartado form: repaints every seat so
// non-matching ones fade to the dimmed color, drops any selection that no
// longer matches the new type, toggles the zone picker panel, and
// refreshes the submit-enabled state.
regresoDateInput?.addEventListener('change', () => setAdminTripType(currentTripType));

function setAdminTripType(type) {
    currentTripType = type;

    const zonePicker = document.getElementById('admin-zone-picker');
    if (zonePicker) zonePicker.classList.toggle('hidden', type !== 'especial' && type !== 'regreso');
    document.getElementById('admin-regreso-date')?.classList.toggle('hidden', type !== 'regreso');

    // Drop any selection that's no longer valid for the new type so the
    // form never submits seats that the server would reject.
    const stale = Array.from(selectedIds).filter((id) => {
        const node = seatNodesById.get(id);
        if (!node) return false;
        return ! isSeatSelectable(node.seat);
    });
    stale.forEach((id) => selectedIds.delete(id));

    if (type === 'especial') {
        completeMancuernas();
    } else {
        showMancuernaWarning([], []);
    }

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
    if (currentTripType === 'especial') {
        const zoneSeats = config.seats.filter((seat) => seat.zone === zoneName && seat.kind === 'seat' && seat.type !== 'disabled');
        const blocked = zoneSeats.filter((seat) => ! selectedIds.has(seat.id) && ! isSeatSelectable(seat));
        if (blocked.length > 0) {
            showMancuernaWarning(zoneSeats.filter((seat) => ! blocked.includes(seat)), blocked);
            return;
        }
        showMancuernaWarning([], []);
    }
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

// Changing the default "Método de pago" select re-applies it to every
// currently selected seat — a quick way to set them all the same way
// before fine-tuning individual seats.
const defaultMethodSelect = document.getElementById('admin-payment-method-select');
if (defaultMethodSelect) {
    defaultMethodSelect.addEventListener('change', () => {
        selectedIds.forEach((id) => seatPaymentMethods.set(id, defaultMethodSelect.value));
        updateForm();
    });
}

document.querySelectorAll('.admin-zone-chip').forEach((chip) => {
    chip.addEventListener('click', () => selectZone(chip.getAttribute('data-zone')));
});

fitStageToContainer();
setAdminTripType(tripTypeSelect ? tripTypeSelect.value : 'one_way');
