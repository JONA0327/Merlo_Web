<?php

namespace App\Models;

use App\Events\SeatAvailabilityUpdated;
use App\Mail\SeatApartadoMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const LEG_OUTBOUND = 'outbound';
    public const LEG_RETURN = 'return';

    protected $fillable = [
        'landing_route_id',
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

    public function getTripTypeLabelAttribute(): string
    {
        if ($this->isReturnLeg()) {
            return 'Solo regreso (reventa)';
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
    public function markGroupPaid(): void
    {
        $group = static::query()
            ->where('id', $this->id)
            ->orWhere('notes', 'group:'.$this->id)
            ->get();

        static::whereIn('id', $group->pluck('id'))->update([
            'payment_status' => self::PAYMENT_COMPLETED,
            'paid_at' => $this->paid_at ?? now(),
        ]);
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
        $group = static::query()
            ->where('id', $this->id)
            ->orWhere('notes', 'group:'.$this->id)
            ->get();

        foreach ($group as $ticket) {
            if ($ticket->isSent() || ! $ticket->customer_email) {
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
     * Scopes the operator's check-in pages use to filter "needs to
     * be verified" reservations (still pending or only one leg done
     * on a round-trip).
     */
    public function scopePendingCheckIn(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where(function ($q1) {
                $q1->where('trip_type', TripTicketPrice::TYPE_ONE_WAY)
                    ->where('leg', self::LEG_RETURN)
                    ->whereNull('return_verified_at');
            })->orWhere(function ($q2) {
                $q2->where('trip_type', TripTicketPrice::TYPE_ONE_WAY)
                    ->where('leg', '!=', self::LEG_RETURN)
                    ->whereNull('outbound_verified_at');
            })->orWhere(function ($q3) {
                $q3->where('trip_type', TripTicketPrice::TYPE_ROUND_TRIP)
                    ->where(function ($q4) {
                        $q4->whereNull('outbound_verified_at')
                            ->orWhereNull('return_verified_at');
                    });
            });
        });
    }
}
