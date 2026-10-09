<?php

namespace App\Models;

use App\Events\SeatAvailabilityUpdated;
use App\Mail\SeatApartadoMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SeatReservation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_COMPLETED = 'completed';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_CHARGEBACK = 'chargeback';

    public const PAYMENT_METHOD_CARD = 'card';
    public const PAYMENT_METHOD_OXXO = 'oxxo';
    public const PAYMENT_METHOD_SPEI = 'spei';
    public const PAYMENT_METHOD_TRANSFER = 'transfer';
    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_TBD = 'tbd';

    public const LEG_OUTBOUND = 'outbound';
    public const LEG_RETURN = 'return';

    // Fixed boarding-point instructions printed on every ticket — the
    // company boards from the same two spots regardless of route, so
    // this isn't modeled as a per-trip field (yet). Written in
    // unambiguous 24h notation: 00:30 (12:30 AM) and 15:30 (3:30 PM) —
    // plain "12:30"/"3:30" reads as noon/3 AM without a meridiem marker.
    // Fallback text used only when the admin hasn't set a custom legend
    // in Configuraciones (Setting::outboundMeetingPoint()/returnMeetingPoint()).
    public const DEFAULT_OUTBOUND_MEETING_POINT = 'Preséntate a las 00:30 en la Alameda, frente a Salud Digna.';
    public const DEFAULT_RETURN_MEETING_POINT = 'Regreso a las 15:30 desde Joaquín Herrera.';

    protected $fillable = [
        'landing_route_id',
        'trip_guide_id',
        'travel_date',
        'bus_unit_seat_id',
        'user_id',
        'trip_type',
        'leg',
        'unit_price',
        'customer_name',
        'customer_email',
        'status',
        'reserved_by',
        'ticket_sent_at',
        'notes',
        'ticket_code',
        'outbound_verified_at',
        'outbound_verified_by',
        'return_verified_at',
        'return_verified_by',
        'return_released_at',
        'return_released_by',
        'return_resale_expires_at',
        'resold_return_reservation_id',
        'source_reservation_id',
        'return_change_requested_at',
        'return_change_deadline',
        'return_changed_to_reservation_id',
        'return_voided_at',
        // OpenPay / payment fields
        'payment_method', 'payment_status', 'subtotal', 'tax', 'total', 'currency',
        'openpay_fee', 'openpay_customer_id', 'openpay_charge_id', 'openpay_authorization',
        'openpay_payment_method', 'openpay_card_brand', 'openpay_card_last4',
        'openpay_card_exp_month', 'openpay_card_exp_year', 'openpay_barcode_url',
        'openpay_barcode', 'openpay_payment_url', 'openpay_expires_at', 'paid_at',
        'openpay_raw_response', 'customer_phone', 'billing_address', 'ip_address',
        'device_fingerprint', 'refunded_at', 'refund_amount', 'refund_reason', 'chargeback_at',
        // Bank transfer fields
        'transfer_reference', 'transfer_proof_path', 'transfer_expires_at',
    ];

    protected $casts = [
        'travel_date' => 'date',
        'ticket_sent_at' => 'datetime',
        'unit_price' => 'float',
        'outbound_verified_at' => 'datetime',
        'return_verified_at' => 'datetime',
        'return_released_at' => 'datetime',
        'return_resale_expires_at' => 'datetime',
        'return_change_requested_at' => 'datetime',
        'return_change_deadline' => 'datetime',
        'return_voided_at' => 'datetime',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'openpay_fee' => 'float',
        'openpay_card_exp_month' => 'integer',
        'openpay_card_exp_year' => 'integer',
        'openpay_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'chargeback_at' => 'datetime',
        'billing_address' => 'array',
        'transfer_expires_at' => 'datetime',
    ];

    public function landingRoute(): BelongsTo
    {
        return $this->belongsTo(LandingRoute::class);
    }

    public function tripGuide(): BelongsTo
    {
        return $this->belongsTo(TripGuide::class);
    }

    /**
     * Still staged in a guide, waiting for the real trip to be created —
     * landing_route_id is null and travel_date carries its intended date.
     */
    public function isGuidePending(): bool
    {
        return $this->landing_route_id === null && $this->trip_guide_id !== null;
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(BusUnitSeat::class, 'bus_unit_seat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function outboundVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outbound_verified_by');
    }

    public function returnVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_verified_by');
    }

    public function returnReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_released_by');
    }

    /**
     * On the ORIGINAL round-trip reservation, the resale ticket that
     * was created once someone bought its released return leg.
     */
    public function resoldReturnReservation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resold_return_reservation_id');
    }

    /**
     * On a resale ticket (leg = return), the original round-trip
     * reservation it was carved from.
     */
    public function sourceReservation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_reservation_id');
    }

    /**
     * On the ORIGINAL round-trip reservation, the replacement one-seat
     * ticket created once the customer picked a new return date via
     * the self-service return-change flow (distinct from
     * resoldReturnReservation(), which is the admin-driven resale path).
     */
    public function returnChangedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'return_changed_to_reservation_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isOneWay(): bool
    {
        return $this->trip_type === TripTicketPrice::TYPE_ONE_WAY;
    }

    public function isRoundTrip(): bool
    {
        return $this->trip_type === TripTicketPrice::TYPE_ROUND_TRIP;
    }

    public function isEspecial(): bool
    {
        return $this->trip_type === TripTicketPrice::TYPE_ESPECIAL;
    }

    /**
     * The price to SHOW for this boleto. unit_price splits an especial
     * mancuerna across its seats (so sums/reports add up), but the
     * customer and staff should see the mancuerna price itself
     * ($1,800 for both seats, not $900 each). Everything else shows
     * its own unit_price.
     */
    public function getDisplayPriceAttribute(): float
    {
        $price = (float) $this->unit_price;
        if (! $this->isEspecial()) {
            return $price;
        }

        $seatsInMancuerna = min(
            TripTicketPrice::SEATS_PER_MANCUERNA,
            $this->groupMembers()->where('trip_type', TripTicketPrice::TYPE_ESPECIAL)->count()
        );

        return round($price * max(1, $seatsInMancuerna), 2);
    }

    /**
     * Formatted display_price, labeled when it's a mancuerna price.
     */
    public function getDisplayPriceLabelAttribute(): string
    {
        return '$'.number_format($this->display_price, 2).($this->isEspecial() ? ' (mancuerna)' : '');
    }

    public function isRegreso(): bool
    {
        return $this->trip_type === TripTicketPrice::TYPE_REGRESO;
    }

    /**
     * "especial" needs both legs verified at check-in, same as a real
     * round trip (see scopePendingCheckIn()) — this is the generalized
     * check views should use instead of isRoundTrip() wherever that
     * was really asking "does this need an outbound AND a return scan".
     */
    public function needsBothLegs(): bool
    {
        return $this->isRoundTrip() || $this->isEspecial();
    }

    /**
     * Boarding-point instructions for whichever leg(s) this ticket
     * actually covers — a return-only ticket (isReturnLeg()) only shows
     * the return point; a one-way outbound ticket only shows the
     * outbound point; round trip / especial show both.
     *
     * @return array<int, string>
     */
    public function boardingLegendLines(): array
    {
        $lines = [];

        if (! $this->isReturnLeg()) {
            $lines[] = Setting::current()->outboundMeetingPoint();
        }

        if ($this->isReturnLeg() || $this->needsBothLegs()) {
            $lines[] = Setting::current()->returnMeetingPoint();
        }

        return $lines;
    }

    public function getTripTypeLabelAttribute(): string
    {
        if ($this->isReturnLeg()) {
            // Two different flows both produce a leg=return row: the
            // existing admin resale (always carries source_reservation_id)
            // and a direct "De regreso" purchase/apartado (no source —
            // nobody released anything, it was just sold/booked as its
            // own product). Distinguish them in the label.
            return $this->source_reservation_id !== null
                ? 'Solo regreso (reventa)'
                : 'Solo regreso';
        }

        if ($this->isOneWay() && $this->source_reservation_id !== null) {
            return 'Regreso reprogramado';
        }

        return TripTicketPrice::tripTypes()[$this->trip_type] ?? $this->trip_type;
    }

    public function getTotalAttribute(): float
    {
        return (float) ($this->unit_price ?? 0);
    }

    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->customer_name ?: ($this->user?->name ?? 'Cliente');
    }

    public function getCustomerDisplayEmailAttribute(): ?string
    {
        return $this->customer_email ?: $this->user?->email;
    }

    /**
     * Check-in helpers. The "leg" is 'outbound' for one-way tickets
     * and either 'outbound' / 'return' for round-trips. The check-in
     * UI uses these to decide which button to show and to block a
     * second scan of the same leg.
     */
    public function isOutboundVerified(): bool
    {
        return $this->outbound_verified_at !== null;
    }

    public function isReturnVerified(): bool
    {
        return $this->return_verified_at !== null;
    }

    public function isFullyCheckedIn(): bool
    {
        if ($this->isReturnLeg()) {
            return $this->isReturnVerified();
        }
        if ($this->isOneWay()) {
            return $this->isOutboundVerified();
        }
        return $this->isOutboundVerified() && $this->isReturnVerified();
    }

    /**
     * Whether this row is a one-way ticket that covers the RETURN
     * calendar leg — i.e. a resold return seat, not a normal outbound
     * one-way purchase.
     */
    public function isReturnLeg(): bool
    {
        return $this->leg === self::LEG_RETURN;
    }

    /**
     * Whether an admin has released this round-trip reservation's
     * return leg for resale (regardless of whether the window is
     * still open).
     */
    public function isReturnReleased(): bool
    {
        return $this->return_released_at !== null;
    }

    public function isReturnResold(): bool
    {
        return $this->resold_return_reservation_id !== null;
    }

    /**
     * Whether the released return leg can still be bought right now:
     * released, not already resold, and within the configured
     * validity window.
     */
    public function isResaleWindowOpen(): bool
    {
        return $this->isReturnReleased()
            && ! $this->isReturnResold()
            && $this->return_resale_expires_at !== null
            && $this->return_resale_expires_at->isFuture();
    }

    /**
     * Self-service return-date change (distinct from the admin-driven
     * resale flow above): whether the customer is currently allowed to
     * request one. Only the round-trip's own row can request it — not
     * yet requested, not already changed/voided, return not boarded,
     * and not already given up for resale by an admin.
     */
    public function canRequestReturnChange(): bool
    {
        return $this->isRoundTrip()
            && $this->isPaymentCompleted()
            && ! $this->isReturnVerified()
            && ! $this->hasRequestedReturnChange()
            && ! $this->isReturnReleased()
            && ! $this->isReturnVoided();
    }

    public function hasRequestedReturnChange(): bool
    {
        return $this->return_change_requested_at !== null;
    }

    /**
     * Requested, no replacement ticket generated yet, and the
     * 5-business-day deadline hasn't lapsed — i.e. the customer can
     * still pick a new return date right now.
     */
    public function isReturnChangePending(): bool
    {
        return $this->hasRequestedReturnChange()
            && $this->return_changed_to_reservation_id === null
            && $this->return_voided_at === null
            && $this->return_change_deadline !== null
            && $this->return_change_deadline->isFuture();
    }

    public function isReturnChangeExpired(): bool
    {
        return $this->hasRequestedReturnChange()
            && $this->return_changed_to_reservation_id === null
            && $this->return_voided_at === null
            && $this->return_change_deadline !== null
            && $this->return_change_deadline->isPast();
    }

    public function hasChangedReturn(): bool
    {
        return $this->return_changed_to_reservation_id !== null;
    }

    public function isReturnVoided(): bool
    {
        return $this->return_voided_at !== null;
    }

    /**
     * Admin-driven return reschedule (distinct from the customer
     * self-service flow above, which has its own request→5-day-window→
     * choose dance): an operator at check-in picks the new date the
     * customer states right there, immediately — no waiting window,
     * but exactly one shot. Available for round-trip AND "especial"
     * (needsBothLegs()), only before the return has actually boarded,
     * and only once — hasChangedReturn() blocks a second reschedule
     * the same way it already blocks the customer flow from stacking.
     *
     * Deliberately does NOT require isPaymentCompleted(): unlike the
     * customer self-service flow (a financial transaction gate), this
     * is an admin handling someone in person at check-in — e.g. a cash
     * "especial" ticket can already have its outbound leg verified
     * while payment_status is still "pending" (not yet confirmed at
     * ventanilla), and the admin should still be able to rebook their
     * return.
     */
    public function canAdminRescheduleReturn(): bool
    {
        return $this->needsBothLegs()
            && ! $this->isPaymentFailed()
            && ! $this->isPaymentRefunded()
            && ! $this->isReturnVerified()
            && ! $this->hasChangedReturn()
            && ! $this->isReturnVoided();
    }

    /**
     * Seats on this trip sold as "solo ida" whose return leg nobody holds
     * yet — the passenger gets off at the destination, so the bus comes
     * back with that seat empty and a "regreso" sale can claim it
     * (leg=return; the unique index is scoped per leg). A seat with any
     * other row on it — a redondo/especial outbound (released ones are
     * handled by the resale flow instead) or an existing return-leg
     * ticket — is never included.
     */
    public static function idaOnlySeatIdsFor(LandingRoute $trip): \Illuminate\Support\Collection
    {
        return $trip->seatReservations()
            ->get(['bus_unit_seat_id', 'trip_type', 'leg'])
            ->groupBy('bus_unit_seat_id')
            ->filter(fn ($rows) => $rows->every(fn (self $r) => $r->leg === self::LEG_OUTBOUND && $r->isOneWay()))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * Departure day of the trip that comes back on $returnDate, judged
     * from $trip: a trip here is one bus going out on `day` and back on
     * `return_date`, so a passenger returning another day rides the
     * same-route trip that leaves that many days earlier. Guides key
     * their apartados by that departure day too (TripGuide::linkTrip()
     * matches travel_date against the real trip's day).
     */
    public static function regresoDepartureDay(LandingRoute $trip, Carbon $returnDate): Carbon
    {
        $offset = $trip->return_date && $trip->day
            ? (int) abs($trip->day->diffInDays($trip->return_date))
            : 0;

        return $returnDate->copy()->startOfDay()->subDays($offset);
    }

    /**
     * Where a "regreso" coming back on $returnDate gets staged when it's
     * not $trip's own return day: the route's guide for that departure
     * day (see TripGuide::forRouteOnDate()), with $seatIds (seats of
     * $trip's bus) mapped onto the guide's bus — same ids when it's the
     * same bus, by label otherwise. Refuses when the real trip for that
     * day already exists (the regreso goes on that trip's own map) or a
     * seat's return leg is already staged that day. $ignoreId skips the
     * reservation being moved itself. Call inside a transaction.
     *
     * @param  array<int|string>  $seatIds
     * @return array{guide: TripGuide, day: Carbon, seat_map: array<int, int>}
     */
    public static function regresoGuideSlot(LandingRoute $trip, Carbon $returnDate, array $seatIds, ?int $ignoreId = null): array
    {
        $day = self::regresoDepartureDay($trip, $returnDate);

        $existing = LandingRoute::query()
            ->where('from', $trip->from)
            ->where('to', $trip->to)
            ->whereDate('day', $day->toDateString())
            ->first();
        abort_if(
            $existing !== null,
            422,
            'Ya existe el viaje del '.$day->format('d/m/Y').' (regreso '.$returnDate->format('d/m/Y').') — aparta el regreso desde ese viaje.'
        );

        $guide = TripGuide::forRouteOnDate($trip->from, $trip->to, $trip->bus_unit_id, $day, 'Creada automáticamente al agendar un regreso desde el viaje #'.$trip->id);

        $seatMap = [];
        if ($guide->bus_unit_id === $trip->bus_unit_id) {
            foreach ($seatIds as $id) {
                $seatMap[(int) $id] = (int) $id;
            }
        } else {
            $labels = BusUnitSeat::whereIn('id', $seatIds)->pluck('label', 'id');
            $guideSeats = $guide->busUnit->seats()->bookable()->get()->keyBy('label');
            foreach ($seatIds as $id) {
                $match = $guideSeats->get($labels->get((int) $id));
                abort_if(! $match, 422, 'El asiento '.($labels->get((int) $id) ?? $id).' no existe en la unidad de la guía para esa fecha.');
                $seatMap[(int) $id] = $match->id;
            }
        }

        // Same rule as AdminTripGuideController::storeReservation(): one
        // return-leg claim per physical seat per date, across guides.
        $taken = self::whereNull('landing_route_id')
            ->whereNotNull('trip_guide_id')
            ->whereIn('bus_unit_seat_id', array_values($seatMap))
            ->whereDate('travel_date', $day->toDateString())
            ->where('leg', self::LEG_RETURN)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('bus_unit_seat_id');

        if ($taken->isNotEmpty()) {
            $labels = BusUnitSeat::whereIn('id', $taken)->pluck('label')->implode(', ');
            abort(409, "Los siguientes asientos ya tienen regreso agendado para esa fecha: {$labels}");
        }

        return ['guide' => $guide, 'day' => $day, 'seat_map' => $seatMap];
    }

    /**
     * Takes this reservation out of its multi-seat apartado (it's moving
     * somewhere else on its own), handing the root role — free-text note,
     * transfer reference — to the next seat when it was the root. Same
     * hand-off AdminSeatReservationController::removeSeat() does.
     */
    public function detachFromGroup(): void
    {
        $others = $this->groupMembers()->reject(fn (self $r) => $r->id === $this->id)->values();
        if ($others->isEmpty()) {
            return;
        }

        if ($this->groupRootId() !== $this->id) {
            $this->update(['notes' => null]);

            return;
        }

        $notes = $this->notes;
        $reference = $this->transfer_reference;
        $expiresAt = $this->transfer_expires_at;
        // transfer_reference is DB-unique — free it before handing it over.
        $this->update(['notes' => null, 'transfer_reference' => null, 'transfer_expires_at' => null]);

        $newRoot = $others->first();
        $newRoot->update([
            'notes' => $notes,
            'transfer_reference' => $reference,
            'transfer_expires_at' => $expiresAt,
            'reserved_by' => $this->reserved_by,
        ]);
        self::whereIn('id', $others->skip(1)->pluck('id'))->update(['notes' => 'group:'.$newRoot->id]);
    }

    /**
     * Every published, open-seat trip on the reverse direction of this
     * reservation's route — the admin's choices when rescheduling a
     * return (see canAdminRescheduleReturn()). Shared by the check-in
     * detail page and the Apartar asientos list so both offer the same
     * options from the same query.
     */
    public function returnRescheduleOptions(): \Illuminate\Support\Collection
    {
        $route = $this->outboundRouteInfo();

        return LandingRoute::query()
            ->where('from', $route['to'])
            ->where('to', $route['from'])
            ->where('is_active', true)
            ->whereNotNull('bus_unit_id')
            ->where('available_seats', '>', 0)
            ->where('day', '>=', now()->toDateString())
            ->orderBy('day')
            ->get();
    }

    /**
     * This reservation's own outbound route (from/to/bus_unit_id) —
     * from its real trip when it has one, or from its TripGuide when
     * it's still guide-staged (landing_route_id null). Both
     * returnRescheduleOptions() and createReturnLegTicket() need this to
     * work for a reservation that's itself not linked to a real trip yet
     * (e.g. a round-trip passenger staged in a guide who says upfront
     * they won't return the same day — see the Guía's own pending list).
     *
     * @return array{from: string, to: string, bus_unit_id: int|null}
     */
    public function outboundRouteInfo(): array
    {
        if ($this->landing_route_id !== null) {
            $this->loadMissing('landingRoute');

            return [
                'from' => $this->landingRoute->from,
                'to' => $this->landingRoute->to,
                'bus_unit_id' => $this->landingRoute->bus_unit_id,
            ];
        }

        $this->loadMissing('tripGuide');

        return [
            'from' => $this->tripGuide->from,
            'to' => $this->tripGuide->to,
            'bus_unit_id' => $this->tripGuide->bus_unit_id,
        ];
    }

    /**
     * True when bus_unit_seat_id points at a seat that doesn't actually
     * belong to this reservation's own trip/guide's bus unit — a "plantilla"
     * switch (TripGuide::remapSeatsTo()) or a guide linking to a real trip
     * on a DIFFERENT bus than it was staged for (TripGuide::linkTrip()) can
     * both leave this stale instead of silently resolving it, so an admin
     * picks the real seat instead of the app guessing wrong. A reservation
     * in this state must never get an automatic WhatsApp send (see
     * SendLinkedTicketNotification) until it's fixed.
     */
    public function hasSeatMismatch(): bool
    {
        if ($this->bus_unit_seat_id === null) {
            return false;
        }

        $this->loadMissing('seat');

        if (! $this->seat) {
            return true;
        }

        $expectedBusUnitId = $this->landing_route_id !== null
            ? $this->loadMissing('landingRoute')->landingRoute?->bus_unit_id
            : $this->loadMissing('tripGuide')->tripGuide?->bus_unit_id;

        return $expectedBusUnitId !== null && $this->seat->bus_unit_id !== $expectedBusUnitId;
    }

    /**
     * Creates a standalone ticket for the reverse-direction leg of this
     * reservation's route on a given date — attached to an already-open
     * trip if one exists, or staged against a TripGuide otherwise (auto-
     * links via TripGuide::linkTrip() the moment a matching trip gets
     * created). Single source of truth for "the customer's return isn't
     * the same day" across three entry points: the admin check-in
     * reschedule action, the Apartar asientos reschedule action, and the
     * "ya sé cuándo regresa" field at apartado-creation time.
     *
     * $overrides lets callers charge for the leg (ida → a brand-new
     * "regreso" sale) instead of the default free/already-paid shape
     * used for round-trip/especial reschedules.
     *
     * @param array<string, mixed> $overrides
     */
    public function createReturnLegTicket(Carbon $date, array $overrides = []): self
    {
        // Works whether this reservation already has a real trip or is
        // itself still staged in a Guía (landing_route_id null) — see
        // outboundRouteInfo().
        $route = $this->outboundRouteInfo();

        // A real trip has to be an actual bus going the reverse
        // direction — you can't put a returning passenger on another
        // CDMX→SLP run. A Guía, though, is just a bookkeeping folder for
        // "this route/bus/date range" — the user's call: the return stays
        // on the SAME guide as the outbound leg (not a separate reversed
        // one), distinguished only by showing red on the map (see
        // colorsFor() in admin-seat-picker.js) and TripGuide::linkTrip()
        // knowing to match it against the reverse-direction trip once
        // one opens.
        $from = $route['to'];
        $to = $route['from'];
        $guideFrom = $route['from'];
        $guideTo = $route['to'];
        $busUnitId = $route['bus_unit_id'];

        $base = array_merge([
            'user_id' => $this->user_id,
            'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
            'leg' => self::LEG_RETURN,
            'source_reservation_id' => $this->id,
            'unit_price' => 0,
            'payment_method' => $this->payment_method,
            'payment_status' => self::PAYMENT_COMPLETED,
            'paid_at' => now(),
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'currency' => 'MXN',
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'status' => self::STATUS_PENDING,
            'notes' => 'Regreso agendado del boleto #'.$this->id,
        ], $overrides);

        $target = LandingRoute::query()
            ->where('from', $from)
            ->where('to', $to)
            ->where('bus_unit_id', $busUnitId)
            ->where('is_active', true)
            ->whereDate('day', $date->toDateString())
            ->first();

        if ($target && $target->hasSeatMap()) {
            return DB::transaction(function () use ($target, $base) {
                $target = LandingRoute::query()->lockForUpdate()->findOrFail($target->id);
                abort_if($target->available_seats < 1, 409, 'Ya no hay asientos disponibles en la fecha elegida.');

                $takenIds = $target->seatReservations()->pluck('bus_unit_seat_id');
                // No allowed_trip_type filter: "ida" bypasses that
                // restriction everywhere else in the app (it can seat
                // anywhere), and these tickets are always trip_type=
                // one_way — filtering by it here excluded the
                // passenger's OWN seat whenever it happened to be tagged
                // for a different category, silently reassigning them
                // somewhere else at random instead.
                $available = $target->busUnit->seats()
                    ->bookable()
                    ->whereNotIn('id', $takenIds)
                    ->get();

                // Same bus_unit as the original (the query above always
                // matches on bus_unit_id), so try the exact same seat
                // first — only falls back to "whichever's free" if that
                // one's already taken by someone else.
                $seat = $available->firstWhere('id', $this->bus_unit_seat_id) ?? $available->first();

                abort_if(! $seat, 409, 'Ya no hay asientos disponibles en la fecha elegida.');

                $ticket = self::create($base + [
                    'landing_route_id' => $target->id,
                    'bus_unit_seat_id' => $seat->id,
                ]);

                $target->decrement('available_seats');

                return $ticket;
            });
        }

        // No trip yet — stage against the SAME guide as the outbound leg
        // (reusing one that already covers this route/date range;
        // stretching the nearest one for this route to include the date
        // if one exists but doesn't reach that far; only creating a brand
        // new one when there's truly none yet for this route at all).
        // Keeps every auto-staged return for a given route under ONE
        // guide instead of spawning a fresh one per date, and avoids ever
        // creating a reverse-direction guide at all.
        return DB::transaction(function () use ($from, $to, $guideFrom, $guideTo, $busUnitId, $date, $base) {
            abort_if(
                LandingRoute::where('from', $from)->where('to', $to)->where('bus_unit_id', $busUnitId)->whereDate('day', $date->toDateString())->exists(),
                422,
                'Ya existe un viaje abierto para esa fecha — selecciónalo de la lista en lugar de agendarlo.'
            );

            $guide = TripGuide::forRouteOnDate($guideFrom, $guideTo, $busUnitId, $date, 'Creada automáticamente al agendar el regreso del boleto #'.$this->id);

            // Scoped to THIS guide and THIS leg specifically — an
            // unscoped query here would treat a same-numbered seat staged
            // in a totally unrelated guide (different route/bus) on the
            // same date as "taken", silently bumping the passenger off
            // their own seat into whatever's left. An outbound claim on
            // this exact seat+date doesn't block a return either — same
            // "different legs, different people" rule as everywhere else.
            $takenIds = self::where('trip_guide_id', $guide->id)
                ->whereNull('landing_route_id')
                ->whereDate('travel_date', $date->toDateString())
                ->where('leg', self::LEG_RETURN)
                ->pluck('bus_unit_seat_id');

            // No allowed_trip_type filter here either — see the matching
            // comment in the real-trip branch above.
            $available = $guide->busUnit->seats()
                ->bookable()
                ->whereNotIn('id', $takenIds)
                ->get();

            if ($guide->bus_unit_id === $busUnitId) {
                // Same bus as the outbound leg — try the exact same seat
                // first, same as always.
                $seat = $available->firstWhere('id', $this->bus_unit_seat_id) ?? $available->first();
                abort_if(! $seat, 409, 'No hay asientos disponibles en esa unidad para esa fecha.');
                $seatId = $seat->id;
            } else {
                // The guide's plantilla changed since the outbound leg was
                // booked — match by seat LABEL instead of id (same idea as
                // TripGuide's own remap helpers). If that exact number is
                // free here, take it. If not (taken, or doesn't exist on
                // this bus), don't silently hand them a random seat —
                // leave the original (now wrong-bus) seat id in place so
                // hasSeatMismatch() flags it and the admin gets the usual
                // "elige un asiento disponible" warning + select instead.
                $label = $this->loadMissing('seat')->seat?->label;
                $sameLabelSeat = $label !== null ? $available->first(fn ($s) => $s->label === $label) : null;
                $seatId = $sameLabelSeat->id ?? $this->bus_unit_seat_id;
            }

            return self::create($base + [
                'trip_guide_id' => $guide->id,
                'travel_date' => $date->toDateString(),
                'bus_unit_seat_id' => $seatId,
            ]);
        });
    }

    /**
     * A fixed deadline (not a rolling one) counted in business days
     * (Mon–Fri) from now — Saturday/Sunday don't count against the
     * customer. Doesn't account for MX public holidays.
     */
    public static function businessDaysFromNow(int $days): \Illuminate\Support\Carbon
    {
        $date = now();
        $added = 0;
        while ($added < $days) {
            $date = $date->addDay();
            if (! $date->isWeekend()) {
                $added++;
            }
        }
        return $date;
    }

    public function isPaymentPending(): bool
    {
        return $this->payment_status === self::PAYMENT_PENDING;
    }

    public function isPaymentCompleted(): bool
    {
        return $this->payment_status === self::PAYMENT_COMPLETED;
    }

    public function isPaymentFailed(): bool
    {
        return $this->payment_status === self::PAYMENT_FAILED;
    }

    public function isPaymentRefunded(): bool
    {
        return $this->payment_status === self::PAYMENT_REFUNDED || $this->payment_status === self::PAYMENT_CHARGEBACK;
    }

    public function isCashPayment(): bool
    {
        return in_array($this->payment_method, [self::PAYMENT_METHOD_OXXO, self::PAYMENT_METHOD_SPEI], true);
    }

    public function isTransfer(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_TRANSFER;
    }

    public function isCash(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_CASH;
    }

    /**
     * Cash and card (ventanilla) apartados both get confirmed the same
     * way: an operator confirms in person that payment was received,
     * then the real QR ticket is generated and printed/sent — unlike
     * transfer, which has its own reference-number validation flow.
     */
    public function needsVentanillaActivation(): bool
    {
        return in_array($this->payment_method, [self::PAYMENT_METHOD_CASH, self::PAYMENT_METHOD_CARD, self::PAYMENT_METHOD_TBD], true);
    }

    public function isTransferExpired(): bool
    {
        return $this->transfer_expires_at !== null && $this->transfer_expires_at->isPast();
    }

    /**
     * Display label for the payment method (Visa, OXXO, SPEI).
     * For card payments, prefers the brand name; falls back to a
     * generic "Tarjeta" if we never got the brand back from OpenPay.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        if ($this->payment_method === self::PAYMENT_METHOD_OXXO) return 'OXXO';
        if ($this->payment_method === self::PAYMENT_METHOD_SPEI) return 'SPEI';
        if ($this->payment_method === self::PAYMENT_METHOD_TRANSFER) return 'Transferencia';
        if ($this->payment_method === self::PAYMENT_METHOD_CASH) return 'Efectivo';
        if ($this->payment_method === self::PAYMENT_METHOD_TBD) return 'Por definir';
        if ($this->payment_method === self::PAYMENT_METHOD_CARD) {
            return strtoupper($this->openpay_card_brand ?? 'Tarjeta');
        }
        return '—';
    }

    public function getPaymentMethodDetailAttribute(): string
    {
        if ($this->payment_method === self::PAYMENT_METHOD_CARD && $this->openpay_card_last4) {
            return strtoupper($this->openpay_card_brand ?? 'Tarjeta').' •••• '.$this->openpay_card_last4;
        }
        return '';
    }

    /**
     * Generate the unforgeable ticket code. Called on create() via
     * the boot() hook below so every new reservation gets one
     * automatically — admins don't need to think about it.
     */
    public static function generateTicketCode(): string
    {
        // 32 chars of [A-Z0-9] — enough entropy that brute-forcing
        // a valid code against the DB is computationally hopeless.
        return strtoupper(Str::random(4))
            .'-'.strtoupper(Str::random(4))
            .'-'.strtoupper(Str::random(4))
            .'-'.strtoupper(Str::random(4))
            .'-'.strtoupper(Str::random(4))
            .'-'.strtoupper(Str::random(4))
            .'-'.strtoupper(Str::random(4));
    }

    /**
     * Build the reference the customer must write as the transfer's
     * "concepto": {salida DDMMYY}{A}{núm. asientos}{iniciales}{folio
     * aleatorio de 4 dígitos}. The trailing folio is what an admin
     * matches against when reconciling their real bank statement — a
     * faked receipt with a guessed folio simply won't be found. Retries
     * on the astronomically unlikely chance of a collision.
     */
    public static function generateTransferReference(\Illuminate\Support\Carbon $departureDay, int $seatCount, string $customerName): string
    {
        $prefix = $departureDay->format('dmy').'A'.$seatCount.static::initialsFor($customerName);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $prefix.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! static::where('transfer_reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not generate a unique transfer reference after 20 attempts.');
    }

    /**
     * Mark this whole purchase group (the primary row plus any linked
     * multi-seat rows) as paid. Needed because only the root row ever
     * gets the OpenPay charge / admin validation applied directly — the
     * per-seat "child" rows are created once at checkout and otherwise
     * never touched again, so without this they'd stay payment_status
     * = pending forever even though they were genuinely paid and even
     * get emailed a ticket by sendGroupTickets().
     */
    /**
     * Every reservation in this purchase/apartado group — resolves the
     * root first if called on a child row (notes = "group:{root_id}"),
     * so it's safe to call from either side instead of assuming the
     * caller already has the root (unlike markGroupPaid() below, which
     * is only ever called on a root by its existing callers).
     */
    public function groupMembers(): \Illuminate\Support\Collection
    {
        $rootId = $this->groupRootId();

        return static::query()
            ->where('id', $rootId)
            ->orWhere('notes', 'group:'.$rootId)
            ->orderBy('id')
            ->get();
    }

    /**
     * This reservation's group root id — itself if it carries the
     * admin's free-text note (or none), or the id parsed out of a
     * "group:{root_id}" marker when it's one of the other seats in a
     * multi-seat apartado.
     */
    public function groupRootId(): int
    {
        return str_starts_with((string) $this->notes, 'group:')
            ? (int) substr($this->notes, strlen('group:'))
            : $this->id;
    }

    public function markGroupPaid(): void
    {
        $group = static::query()
            ->where('id', $this->id)
            ->orWhere('notes', 'group:'.$this->id)
            ->get();

        // Only the seats paid the same way as this row — a group can mix
        // transfer + cash, and confirming one must not mark the other paid.
        static::whereIn('id', $group->where('payment_method', $this->payment_method)->pluck('id'))->update([
            'payment_status' => self::PAYMENT_COMPLETED,
            'paid_at' => $this->paid_at ?? now(),
        ]);
    }

    /**
     * Payment is per seat (method AND paid/pending can differ inside one
     * apartado), but everything downstream — Pagos confirmation, the
     * WhatsApp notice/ticket — works per group. So after creating or
     * editing, split the group into one group per (method, status): the
     * partition holding the current root keeps it, every other one gets
     * its lowest-id seat as a new root (with its own transfer reference
     * when it's a pending transfer).
     *
     * @return \Illuminate\Support\Collection<int, SeatReservation> root of each resulting group
     */
    public function regroupByPayment(): \Illuminate\Support\Collection
    {
        $members = $this->groupMembers();
        $root = $members->firstWhere('id', $this->groupRootId()) ?? $members->first();
        $roots = collect([$root]);

        foreach ($members->groupBy(fn (self $m) => $m->payment_method.'|'.$m->payment_status) as $rows) {
            if ($rows->contains('id', $root->id)) {
                continue;
            }

            $newRoot = $rows->sortBy('id')->first();
            $newRoot->update([
                'notes' => null,
                'reserved_by' => $root->reserved_by,
                'transfer_reference' => null,
                'transfer_expires_at' => null,
            ]);
            static::whereIn('id', $rows->pluck('id')->reject(fn ($id) => $id === $newRoot->id))
                ->update(['notes' => 'group:'.$newRoot->id]);

            $roots->push($newRoot);
        }

        // A pending transfer needs a reference for Pagos to validate
        // against — a seat switched to transfer after the fact has none.
        foreach ($roots as $r) {
            if ($r->isTransfer() && $r->isPaymentPending() && ! $r->transfer_reference) {
                $r->update([
                    'transfer_reference' => static::generateTransferReference(
                        $r->loadMissing('landingRoute')->landingRoute?->day ?? $r->travel_date ?? now(),
                        $r->groupMembers()->count(),
                        (string) $r->customer_name
                    ),
                    'transfer_expires_at' => now()->addDays(3),
                ]);
            }
        }

        return $roots;
    }

    private static function initialsFor(string $name): string
    {
        $words = array_filter(explode(' ', Str::ascii($name)));

        return strtoupper(implode('', array_map(fn ($w) => $w[0], $words))) ?: 'X';
    }

    /**
     * Email the digital ticket (SeatApartadoMail) to every reservation
     * row in this purchase group — the primary row plus any linked
     * multi-seat rows (notes = "group:{id}") — once payment is
     * confirmed. Safe to call more than once: rows already marked
     * "sent" are skipped.
     */
    public function sendGroupTickets(): void
    {
        $group = $this->groupMembers();

        foreach ($group as $ticket) {
            // Never email a QR for a seat that isn't paid yet.
            if ($ticket->isSent() || ! $ticket->customer_email || ! $ticket->isPaymentCompleted()) {
                continue;
            }

            try {
                Mail::to($ticket->customer_email)->send(new SeatApartadoMail($ticket));
            } catch (\Throwable $e) {
                Log::warning('Ticket mail failed for reservation '.$ticket->id.': '.$e->getMessage());
                continue;
            }

            $ticket->update(['status' => self::STATUS_SENT, 'ticket_sent_at' => now()]);
        }
    }

    /**
     * Cancel this purchase group (the primary row plus any linked
     * multi-seat rows) and give the seats back — used both when an
     * admin rejects a bank transfer and when the 3-day pending-transfer
     * window lapses unvalidated. Deleting the rows (not just flagging
     * payment_status) matters: takenIds/availability checks elsewhere
     * treat ANY existing reservation row as occupying its seat,
     * regardless of payment status.
     */
    public function releaseGroupAndFreeSeat(): void
    {
        $group = static::query()
            ->where('id', $this->id)
            ->orWhere('notes', 'group:'.$this->id)
            ->get();

        if ($group->isEmpty()) {
            return;
        }

        $landingRouteId = $this->landing_route_id;
        $seatIds = $group->pluck('bus_unit_seat_id')->all();
        // Resold return legs never decremented available_seats when
        // purchased (that capacity was already spent by the original
        // round-trip reservation) — releasing them must not inflate the
        // count. Instead, un-claim the original so the resale window
        // (if still open) can be sold again.
        $normalSeatCount = $group->reject(fn (self $r) => $r->isReturnLeg())->count();
        $sourceIds = $group->where('leg', self::LEG_RETURN)->pluck('source_reservation_id')->filter();

        DB::transaction(function () use ($group, $landingRouteId, $normalSeatCount, $sourceIds) {
            if ($normalSeatCount > 0) {
                LandingRoute::whereKey($landingRouteId)->increment('available_seats', $normalSeatCount);
            }
            if ($sourceIds->isNotEmpty()) {
                static::whereIn('id', $sourceIds)->update(['resold_return_reservation_id' => null]);
            }
            static::whereIn('id', $group->pluck('id'))->delete();
        });

        SeatAvailabilityUpdated::dispatchSafely(
            $landingRouteId,
            collect($seatIds)->map(fn ($id) => ['id' => (int) $id, 'status' => 'available'])->all()
        );
    }

    protected static function booted(): void
    {
        static::creating(function (SeatReservation $reservation) {
            if (empty($reservation->ticket_code)) {
                $reservation->ticket_code = static::generateTicketCode();
            }
        });
    }

    /**
     * Payment methods offered when apartando from the admin — the
     * options of the "Método de pago" filter on Apartados and Guías.
     */
    public static function adminPaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_TRANSFER => 'Transferencia',
            self::PAYMENT_METHOD_CARD => 'Tarjeta',
            self::PAYMENT_METHOD_CASH => 'Efectivo',
            self::PAYMENT_METHOD_TBD => 'Por definir',
        ];
    }

    /**
     * The Apartados / Guías list filters (see the
     * admin.partials.reservation-filters form): name search, pagado
     * sí/no, categoría and método de pago. Empty values are ignored.
     */
    public function scopeListFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $paid = $filters['paid'] ?? '';
        $tripType = $filters['trip_type'] ?? '';
        $method = $filters['payment_method'] ?? '';

        return $query
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('customer_name', 'like', '%'.$search.'%')
                ->orWhere('customer_phone', 'like', '%'.$search.'%')))
            ->when($paid === 'yes', fn ($q) => $q->where('payment_status', self::PAYMENT_COMPLETED))
            ->when($paid === 'no', fn ($q) => $q->where(fn ($q2) => $q2->whereNull('payment_status')->orWhere('payment_status', '!=', self::PAYMENT_COMPLETED)))
            ->when(array_key_exists($tripType, TripTicketPrice::tripTypes()), fn ($q) => $q->where('trip_type', $tripType))
            ->when(array_key_exists($method, self::adminPaymentMethods()), fn ($q) => $q->where('payment_method', $method));
    }

    /**
     * Scopes the operator's check-in pages use to filter "needs to
     * be verified" reservations (still pending or only one leg done
     * on a round-trip).
     */
    public function scopePendingCheckIn(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where(function ($q1) {
                $q1->whereIn('trip_type', [TripTicketPrice::TYPE_ONE_WAY, TripTicketPrice::TYPE_REGRESO])
                    ->where('leg', self::LEG_RETURN)
                    ->whereNull('return_verified_at');
            })->orWhere(function ($q2) {
                $q2->where('trip_type', TripTicketPrice::TYPE_ONE_WAY)
                    ->where('leg', '!=', self::LEG_RETURN)
                    ->whereNull('outbound_verified_at');
            })->orWhere(function ($q3) {
                // "especial" is treated like round_trip for check-in:
                // both legs must be verified, regardless of which one
                // is still missing.
                $q3->whereIn('trip_type', [TripTicketPrice::TYPE_ROUND_TRIP, TripTicketPrice::TYPE_ESPECIAL])
                    ->where(function ($q4) {
                        $q4->whereNull('outbound_verified_at')
                            ->orWhereNull('return_verified_at');
                    });
            });
        });
    }
}
