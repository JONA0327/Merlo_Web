<?php

namespace App\Models;

use App\Mail\SeatApartadoMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $fillable = [
        'landing_route_id',
        'bus_unit_seat_id',
        'user_id',
        'trip_type',
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
        // OpenPay / payment fields
        'payment_method', 'payment_status', 'subtotal', 'tax', 'total', 'currency',
        'openpay_fee', 'openpay_customer_id', 'openpay_charge_id', 'openpay_authorization',
        'openpay_payment_method', 'openpay_card_brand', 'openpay_card_last4',
        'openpay_card_exp_month', 'openpay_card_exp_year', 'openpay_barcode_url',
        'openpay_barcode', 'openpay_payment_url', 'openpay_expires_at', 'paid_at',
        'openpay_raw_response', 'customer_phone', 'billing_address', 'ip_address',
        'device_fingerprint', 'refunded_at', 'refund_amount', 'refund_reason', 'chargeback_at',
    ];

    protected $casts = [
        'ticket_sent_at' => 'datetime',
        'unit_price' => 'float',
        'outbound_verified_at' => 'datetime',
        'return_verified_at' => 'datetime',
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
        if ($this->isOneWay()) {
            return $this->isOutboundVerified();
        }
        return $this->isOutboundVerified() && $this->isReturnVerified();
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

    /**
     * Display label for the payment method (Visa, OXXO, SPEI).
     * For card payments, prefers the brand name; falls back to a
     * generic "Tarjeta" if we never got the brand back from OpenPay.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        if ($this->payment_method === self::PAYMENT_METHOD_OXXO) return 'OXXO';
        if ($this->payment_method === self::PAYMENT_METHOD_SPEI) return 'SPEI';
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
                    ->whereNull('outbound_verified_at');
            })->orWhere(function ($q2) {
                $q2->where('trip_type', TripTicketPrice::TYPE_ROUND_TRIP)
                    ->where(function ($q3) {
                        $q3->whereNull('outbound_verified_at')
                            ->orWhereNull('return_verified_at');
                    });
            });
        });
    }
}
