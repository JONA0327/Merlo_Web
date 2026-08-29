import Konva from 'konva';
import axios from 'axios';
import './echo';

const config = window.__SEAT_PICKER__;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const SEAT_SIZE = 40;
const HOLD_MINUTES = 10;

// Per-trip-type prices keyed by the same constants the controller uses.
// When the customer toggles "Solo ida" / "Viaje redondo", the seat
// picker reads from this map to update the subtotal and the hidden
// trip_type form input.
const PRICE_BY_TYPE = {
    one_way: Number(config.priceOneWay ?? 0),
    round_trip: Number(config.priceRoundTrip ?? 0),
};
let currentTripType = config.defaultTripType === 'round_trip' ? 'round_trip' : 'one_way';

const AVAILABLE_COLORS = { fill: '#22C55E', stroke: '#15803D' };
// "Other-type" seat: this seat is bookable in general, but NOT for
// the trip type the customer currently has selected. Render it as a
// dimmed version of the regular seat so it reads as "off" without
// disappearing entirely (the customer might toggle to see the other
// prices and want a sense of the layout).
const OTHER_TYPE_COLORS = { fill: '#E5E7EB', stroke: '#9CA3AF' };
const HELD_COLORS = { fill: '#FACC15', stroke: '#A16207' };
const PURCHASED_COLORS = { fill: '#EF4444', stroke: '#991B1B' };
const DISABLED_COLORS = { fill: '#D1D5DB', stroke: '#6B7280' };
const OBJECT_COLORS = { fill: '#94A3B8', stroke: '#475569' };
const OUTLINE_DEFAULT_COLOR = '#2B1113';
// VIP is layered as a gold ring around whichever status color applies —
// "is VIP" and "is available/held/sold" are two independent facts, not one
// more color to memorize.
const VIP_ACCENT_STROKE = '#F5B301';

// Flat, single-color pictograms — reads like real signage instead of relying
// on a platform's colorful emoji font. Mirrors the same shapes drawn in the
// admin editor (resources/js/seat-editor.js) so seats/objects look identical
// in both views.
const ICON_TYPES = new Set(['door', 'stairs', 'driver', 'bathroom', 'table']);

function buildIconNode(type, width, height, color) {
    const group = new Konva.Group({ listening: false });
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

function hexToRgba(hex, alpha) {
    const clean = (hex || OUTLINE_DEFAULT_COLOR).replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// The admin's editing canvas is deliberately oversized (room to work with),
// but customers should only ever see the actual floor plan — not a big
// mostly-empty rectangle with the bus tucked in one corner. Fit the stage
// tightly around wherever the seats/objects actually are instead of trusting
// the admin-configured canvas_width/canvas_height.
const CONTENT_PADDING = 24;

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
    container: 'seat-canvas',
    width: naturalWidth,
    height: naturalHeight,
});
const layer = new Konva.Layer(contentOffset);
stage.add(layer);

// Shrink (never enlarge) the whole plan to fit whatever width the card
// actually has, so the customer is never stuck horizontally scrolling to
// see the full map — this is what actually controls the on-screen size now,
// not the admin's Ancho/Alto in "Datos de la unidad" (those just give the
// admin room to arrange things in the editor).
function fitStageToContainer() {
    const container = document.getElementById('seat-canvas');
    if (!container) return;

    const availableWidth = container.clientWidth;
    const scale = availableWidth > 0 && availableWidth < naturalWidth ? availableWidth / naturalWidth : 1;

    stage.scale({ x: scale, y: scale });
    stage.width(naturalWidth * scale);
    stage.height(naturalHeight * scale);
    stage.batchDraw();
}

window.addEventListener('resize', fitStageToContainer);
fitStageToContainer();

const form = document.getElementById('seat-form');
const countEl = document.getElementById('seat-count');
const submitButton = document.getElementById('seat-submit');
const deckLowerTab = document.getElementById('deck-lower');
const deckUpperTab = document.getElementById('deck-upper');
const subtotalEl = document.getElementById('seat-subtotal');
const selectedListEl = document.getElementById('selected-seats-list');
const countdownEl = document.getElementById('seat-countdown');
const alertEl = document.getElementById('seat-picker-alert');

let currentDeck = 'lower';

// Seat status is now live, not baked in at page load: purchasedIds mirrors
// permanent SeatReservation rows, heldByOther/heldByMe mirror active
// SeatHold rows (split by who holds them). selectedIds always equals
// heldByMe's keys — kept as its own Set because that's what the existing
// hidden-input/summary code already expects.
const purchasedIds = new Set(config.takenIds);
const heldByOther = new Map();
const heldByMe = new Map();
const selectedIds = new Set();
const pendingIds = new Set();
const seatNodesById = new Map();

let alertTimeout = null;

function showAlert(message) {
    if (!alertEl) return;
    alertEl.textContent = message;
    alertEl.classList.remove('hidden');
    clearTimeout(alertTimeout);
    alertTimeout = setTimeout(() => alertEl.classList.add('hidden'), 4000);
}

function isSelectable(seat) {
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (purchasedIds.has(seat.id)) return false;
    if (heldByOther.has(seat.id)) return false;
    // Per-trip-type restriction set by the admin on the seat editor.
    // A seat flagged 'one_way' is not bookable when the customer is on
    // the round-trip toggle (and vice versa). 'both' is unrestricted.
    const allowed = seat.allowed_trip_type ?? 'both';
    if (allowed !== 'both' && allowed !== currentTripType) return false;
    return true;
}

// Like isSelectable but excluding the trip-type filter — used by
// colorsFor so non-matching seats still show up as "dimmed" instead
// of dropping off the canvas. Clicks on them are still blocked by
// the listening=false flip below.
function isOtherType(seat) {
    if (seat.kind === 'object' || seat.type === 'disabled') return false;
    if (purchasedIds.has(seat.id)) return false;
    if (heldByOther.has(seat.id)) return false;
    const allowed = seat.allowed_trip_type ?? 'both';
    return allowed !== 'both' && allowed !== currentTripType;
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
    if (purchasedIds.has(seat.id)) return PURCHASED_COLORS;
    if (heldByOther.has(seat.id) || heldByMe.has(seat.id)) return HELD_COLORS;
    if (isOtherType(seat)) return OTHER_TYPE_COLORS;
    return AVAILABLE_COLORS;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
}

function updateSummary() {
    countEl.textContent = selectedIds.size;
    submitButton.disabled = selectedIds.size === 0;

    if (subtotalEl) {
        const unitPrice = PRICE_BY_TYPE[currentTripType] ?? 0;
        subtotalEl.textContent = formatCurrency(unitPrice * selectedIds.size);
    }

    if (selectedListEl) {
        selectedListEl.replaceChildren();
        const labels = Array.from(selectedIds)
            .map((id) => seatNodesById.get(id)?.seat.label)
            .filter(Boolean)
            .sort();

        if (labels.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-xs text-[#2B1113]/40';
            empty.textContent = 'Selecciona un asiento para verlo aquí.';
            selectedListEl.appendChild(empty);
        } else {
            labels.forEach((label) => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center rounded-lg bg-[#FACC15]/20 px-2.5 py-1 text-xs font-bold text-[#A16207] ring-1 ring-[#A16207]/30';
                chip.textContent = label;
                selectedListEl.appendChild(chip);
            });
        }
    }
}

// Trip type toggle (Solo ida / Viaje redondo). Updates the price, the
// hidden form input, repaints every seat so non-matching ones fall into
// the dimmed "other-type" color, and re-evaluates which seats are
// clickable.
function setTripType(type) {
    if (type !== 'one_way' && type !== 'round_trip') return;
    currentTripType = type;

    const input = document.getElementById('trip-type-input');
    if (input) input.value = type;

    // Toggle the visual active state on the two buttons.
    const tabs = document.querySelectorAll('[data-trip-type]');
    tabs.forEach((tab) => {
        const isActive = tab.getAttribute('data-trip-type') === type;
        tab.classList.toggle('bg-[#8C1D2B]', isActive);
        tab.classList.toggle('text-white', isActive);
        tab.classList.toggle('text-[#2B1113]/60', !isActive);
    });

    // Repaint the whole layer: the listening state on the Konva groups
    // flips too, so clicks on a now-disabled seat fall through to the
    // empty area below.
    config.seats.forEach((seat) => {
        const node = seatNodesById.get(seat.id);
        if (!node) return;
        const colors = colorsFor(seat);
        node.rect.fill(colors.fill);
        node.rect.stroke(colors.stroke);
        node.group.listening(isSelectable(seat));
    });
    layer.batchDraw();
    updateSummary();
}

document.querySelectorAll('[data-trip-type]').forEach((tab) => {
    tab.addEventListener('click', () => setTripType(tab.getAttribute('data-trip-type')));
});

function toggleHiddenInput(seatId, isSelected) {
    const existing = form.querySelector(`input[name="seat_ids[]"][value="${seatId}"]`);

    if (isSelected && !existing) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'seat_ids[]';
        input.value = seatId;
        form.appendChild(input);
    } else if (!isSelected && existing) {
        existing.remove();
    }
}

function repaintSeat(seatId) {
    const node = seatNodesById.get(seatId);
    if (!node) return;

    const colors = colorsFor(node.seat);
    node.rect.fill(colors.fill);
    node.rect.stroke(colors.stroke);
    node.group.listening(isSelectable(node.seat));
    layer.batchDraw();
}

function holdUrl(seatId) {
    return config.holdUrlBase.replace('__SEAT__', seatId);
}

async function selectSeat(seat) {
    heldByMe.set(seat.id, Date.now() + HOLD_MINUTES * 60 * 1000);
    selectedIds.add(seat.id);
    toggleHiddenInput(seat.id, true);
    repaintSeat(seat.id);
    updateSummary();

    try {
        const { data } = await axios.post(holdUrl(seat.id));
        heldByMe.set(seat.id, Date.parse(data.expiresAt));
    } catch (error) {
        heldByMe.delete(seat.id);
        selectedIds.delete(seat.id);
        toggleHiddenInput(seat.id, false);
        repaintSeat(seat.id);
        updateSummary();
        showAlert(error.response?.data?.message ?? 'Ese asiento ya no está disponible.');
    }
}

async function releaseSeat(seat) {
    heldByMe.delete(seat.id);
    selectedIds.delete(seat.id);
    toggleHiddenInput(seat.id, false);
    repaintSeat(seat.id);
    updateSummary();

    try {
        await axios.delete(holdUrl(seat.id));
    } catch {
        // Non-fatal — the hold self-expires in at most 10 minutes regardless.
    }
}

async function handleSeatClick(seat) {
    if (pendingIds.has(seat.id)) return;

    pendingIds.add(seat.id);
    try {
        if (heldByMe.has(seat.id)) {
            await releaseSeat(seat);
        } else {
            await selectSeat(seat);
        }
    } finally {
        pendingIds.delete(seat.id);
    }
}

const groupsByDeck = { lower: [], upper: [] };
const outlineGroups = [];
const dividerGroups = [];

config.heldSeats.forEach((hold) => {
    const expiresAtMs = Date.parse(hold.expires_at);
    if (hold.user_id === config.selfUserId) {
        heldByMe.set(hold.bus_unit_seat_id, expiresAtMs);
    } else {
        heldByOther.set(hold.bus_unit_seat_id, expiresAtMs);
    }
});

config.seats.forEach((seat) => {
    const colors = colorsFor(seat);
    const deck = seat.deck ?? 'lower';

    const width = seat.width ?? SEAT_SIZE;
    const height = seat.height ?? SEAT_SIZE;

    const group = new Konva.Group({
        x: seat.pos_x,
        y: seat.pos_y,
        listening: isSelectable(seat),
    });

    if (seat.type === 'vip' && seat.kind !== 'object') {
        const pad = 3;
        const ring = seat.shape === 'circle'
            ? new Konva.Ellipse({
                x: width / 2, y: height / 2, radiusX: width / 2 + pad, radiusY: height / 2 + pad,
                stroke: VIP_ACCENT_STROKE, strokeWidth: 2, listening: false,
            })
            : new Konva.Rect({
                x: -pad, y: -pad, width: width + pad * 2, height: height + pad * 2,
                cornerRadius: (seat.corner_radius ?? 8) + pad, stroke: VIP_ACCENT_STROKE, strokeWidth: 2, listening: false,
            });
        group.add(ring);
    }

    const rect = seat.shape === 'circle'
        ? new Konva.Ellipse({
            x: width / 2,
            y: height / 2,
            radiusX: width / 2,
            radiusY: height / 2,
            fill: colors.fill,
            stroke: colors.stroke,
            strokeWidth: seat.border_width ?? 2,
        })
        : new Konva.Rect({
            width,
            height,
            fill: colors.fill,
            stroke: colors.stroke,
            strokeWidth: seat.border_width ?? 2,
            cornerRadius: seat.corner_radius ?? 8,
        });

    const wantsIcon = seat.kind === 'object' && ICON_TYPES.has(seat.type);
    const isDivider = seat.kind === 'object' && seat.type === 'divider';
    const isOutline = seat.kind === 'object' && seat.type === 'outline';
    // The outline's own label is irrelevant to riders — it always shows the
    // unit's number so they can tell which bus map they're looking at.
    const outlineText = config.unitName ?? seat.label;

    const text = new Konva.Text({
        text: wantsIcon || isDivider ? '' : isOutline ? outlineText : seat.label,
        width,
        height,
        align: 'center',
        verticalAlign: 'middle',
        fontSize: 10,
        fontStyle: 'bold',
        fill: '#2B1113',
        listening: false,
    });

    group.add(rect);
    group.add(text);
    if (wantsIcon) {
        group.add(buildIconNode(seat.type, width, height, colors.stroke));
    }
    group.visible(deck === currentDeck);

    seatNodesById.set(seat.id, { group, rect, seat });

    if (seat.kind !== 'object' && seat.type !== 'disabled') {
        group.on('click tap', () => handleSeatClick(seat));

        group.on('mouseenter', () => {
            if (isSelectable(seat)) stage.container().style.cursor = 'pointer';
        });
        group.on('mouseleave', () => {
            stage.container().style.cursor = 'default';
        });
    }

    layer.add(group);
    groupsByDeck[deck].push(group);
    if (seat.kind === 'object' && seat.type === 'outline') outlineGroups.push(group);
    if (seat.kind === 'object' && seat.type === 'divider') dividerGroups.push(group);
});

// Outline and divider objects are background decoration and must never
// visually cover the actual seats, regardless of the order they came back
// in. moveToBottom() puts whatever it's called on LAST at the very back, so
// dividers go first (ending up just above the outline) and the outline goes
// last (furthest back of all) — both still behind every seat either way.
dividerGroups.forEach((group) => group.moveToBottom());
outlineGroups.forEach((group) => group.moveToBottom());

// A reload mid-hold should look exactly like it did before the reload.
heldByMe.forEach((expiresAt, seatId) => {
    selectedIds.add(seatId);
    toggleHiddenInput(seatId, true);
});

function switchDeck(deck) {
    currentDeck = deck;

    groupsByDeck.lower.forEach((group) => group.visible(deck === 'lower'));
    groupsByDeck.upper.forEach((group) => group.visible(deck === 'upper'));

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

if (deckLowerTab && deckUpperTab) {
    deckLowerTab.addEventListener('click', () => switchDeck('lower'));
    deckUpperTab.addEventListener('click', () => switchDeck('upper'));
}

switchDeck('lower');
layer.draw();

// Apply the default trip type to the seat layer — must happen after
// seatNodesById is populated, otherwise the first paint would treat
// all seats as "available" regardless of the per-seat trip-type tag.
setTripType(currentTripType);
updateSummary();

/* ---------- Live updates (Reverb + Echo) ----------
 * One public channel per trip — no user-identifying info is broadcast
 * (just which seat, which status, and who holds it), so no channel
 * authorization is needed. If the WebSocket connection never comes up
 * (e.g. Reverb isn't running), this simply never fires and the page still
 * works via plain HTTP — it just loses cross-tab live sync.
 */
if (window.Echo && config.landingRouteId) {
    window.Echo.channel(`seat-availability.${config.landingRouteId}`)
        .listen('.seat.status.updated', (e) => {
            e.seats.forEach(applyRemoteUpdate);
        });
}

function applyRemoteUpdate(update) {
    // Coerce defensively — seatNodesById is keyed by the numbers straight out
    // of config.seats, but a broadcast payload built from form-submitted data
    // server-side can come through as a numeric string; a strict Map lookup
    // ("168" !== 168) would otherwise silently drop the update.
    const seatId = Number(update.id);
    const node = seatNodesById.get(seatId);
    if (!node) return;

    if (update.status === 'purchased') {
        purchasedIds.add(seatId);
        heldByOther.delete(seatId);
        if (heldByMe.has(seatId)) {
            heldByMe.delete(seatId);
            selectedIds.delete(seatId);
            toggleHiddenInput(seatId, false);
        }
    } else if (update.status === 'held') {
        if (update.heldBy === config.selfUserId) {
            heldByMe.set(seatId, Date.parse(update.expiresAt));
            heldByOther.delete(seatId);
        } else {
            heldByOther.set(seatId, Date.parse(update.expiresAt));
            if (heldByMe.has(seatId)) {
                heldByMe.delete(seatId);
                selectedIds.delete(seatId);
                toggleHiddenInput(seatId, false);
                showAlert(`El asiento ${node.seat.label} ya lo tomó otra persona.`);
            }
        }
    } else if (update.status === 'available') {
        heldByOther.delete(seatId);
        if (!pendingIds.has(seatId)) {
            heldByMe.delete(seatId);
        }
    }

    repaintSeat(seatId);
    updateSummary();
}

/* ---------- Client-side hold countdown ----------
 * The server is always the real authority on expiry, but the UI shouldn't
 * silently sit on a stale "held" seat for up to 10 minutes if a broadcast
 * was missed — this ticks the visible countdown and self-releases locally
 * (plus nudges the server) the moment the local clock crosses expiry.
 */
function updateCountdownDisplay(expiresAt) {
    if (!countdownEl) return;

    if (!expiresAt) {
        countdownEl.classList.add('hidden');
        countdownEl.textContent = '';
        return;
    }

    const remainingMs = Math.max(0, expiresAt - Date.now());
    const minutes = Math.floor(remainingMs / 60000);
    const seconds = Math.floor((remainingMs % 60000) / 1000);
    countdownEl.classList.remove('hidden');
    countdownEl.textContent = `Tus asientos se liberan en ${minutes}:${String(seconds).padStart(2, '0')}`;
}

function tickCountdown() {
    const now = Date.now();
    let soonestExpiry = null;

    heldByMe.forEach((expiresAt, seatId) => {
        if (expiresAt <= now) {
            heldByMe.delete(seatId);
            selectedIds.delete(seatId);
            toggleHiddenInput(seatId, false);
            repaintSeat(seatId);
            axios.delete(holdUrl(seatId)).catch(() => {});
        } else if (soonestExpiry === null || expiresAt < soonestExpiry) {
            soonestExpiry = expiresAt;
        }
    });

    updateCountdownDisplay(soonestExpiry);
    updateSummary();
}

tickCountdown();
setInterval(tickCountdown, 1000);
