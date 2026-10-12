<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LandingRoute extends Model
{
    use HasFactory;

    protected $table = 'landing_routes';

    protected $fillable = [
        'from',
        'to',
        'day',
        'recurring_weekdays',
        'return_date',
        'departure_time',
        'available_seats',
        'bus_unit_id',
        'is_active',
        'featured',
        'sort_order',
        'image',
        'boarding_outbound_legend',
        'boarding_return_legend',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
        'available_seats' => 'integer',
        'day' => 'date',
        'return_date' => 'date',
        'recurring_weekdays' => 'array',
    ];

    /**
     * Spanish weekday abbreviations in week order (Sunday first — matches
     * Carbon's 0-6 dayOfWeek convention, which is what recurring_weekdays
     * stores).
     */
    private const WEEKDAY_LABELS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    public function getDepartureTimeFormattedAttribute(): ?string
    {
        if (! $this->departure_time) {
            return null;
        }

        return Carbon::parse($this->departure_time)->format('H:i');
    }

    public function busUnit(): BelongsTo
    {
        return $this->belongsTo(BusUnit::class);
    }

    public function seatReservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function seatHolds(): HasMany
    {
        return $this->hasMany(SeatHold::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TripTicketPrice::class);
    }

    public function hasSeatMap(): bool
    {
        return $this->bus_unit_id !== null;
    }

    /**
     * "Días recurrentes" mode (e.g. "corre Dom, Lun, Mar") instead of the
     * original "fecha específica" mode. A recurring row (day=null) is
     * only ever the pattern — activateRecurringFor() spawns the real,
     * dated trip Apartar asientos actually sells seats against. Mutually
     * exclusive with `day` in practice: store()/update() never set both.
     */
    public function isRecurring(): bool
    {
        return ! empty($this->recurring_weekdays);
    }

    /**
     * The other ACTIVE trip, if any, that already commits $busUnitId to
     * a day this trip would also run on — a physical bus can't run two
     * DIFFERENT routes the same day, whether both are specific-date
     * trips, both recurring, or one of each (checked by weekday either
     * way). Same from+to+bus_unit_id is never a conflict — that's a
     * recurring template and its own already-activated daily instances
     * (see activateRecurringFor()), not two routes fighting over a bus.
     * $excludeId additionally skips the trip being edited so re-saving
     * it doesn't conflict with itself.
     */
    public static function busUnitConflict(string $from, string $to, int $busUnitId, ?string $day, ?array $recurringWeekdays, ?int $excludeId = null): ?self
    {
        $weekdays = ! empty($recurringWeekdays) ? $recurringWeekdays : ($day ? [Carbon::parse($day)->dayOfWeek] : []);
        if (empty($weekdays)) {
            return null;
        }

        return static::where('bus_unit_id', $busUnitId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('from', '!=', $from)->orWhere('to', '!=', $to))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->first(function (self $other) use ($weekdays) {
                $otherWeekdays = $other->isRecurring()
                    ? $other->recurring_weekdays
                    : ($other->day ? [$other->day->dayOfWeek] : []);

                return ! empty(array_intersect($weekdays, $otherWeekdays));
            });
    }

    public function recurringWeekdaysLabel(): ?string
    {
        if (! $this->isRecurring()) {
            return null;
        }

        return collect($this->recurring_weekdays)
            ->sort()
            ->map(fn ($day) => self::WEEKDAY_LABELS[$day] ?? '?')
            ->implode(', ');
    }

    /**
     * The "next phase" isRecurring() used to promise: a recurring row
     * (day=null) is only ever a pattern — Apartar asientos needs a real,
     * dated trip to sell seats against, same as any other. For every
     * active recurring trip whose pattern covers $date's weekday, this
     * ensures a real same-day instance exists (skips it if one already
     * does — safe to call repeatedly, same shape as
     * TripGuide::ensureForRecurringTrip()). Each new instance copies the
     * template's prices and boarding legends, then runs through the same
     * TripGuide::linkTrip() / StandingReservation::applyToTrip() a
     * manually-created trip gets, so apartados already staged in its
     * guide (and "de planta" seats) attach immediately.
     *
     * @return Collection<int, self> newly activated instances
     */
    public static function activateRecurringFor(Carbon $date): Collection
    {
        $templates = static::where('is_active', true)
            ->whereNotNull('recurring_weekdays')
            ->with('prices')
            ->get()
            ->filter(fn (self $t) => in_array($date->dayOfWeek, $t->recurring_weekdays ?? [], true));

        $activated = collect();

        foreach ($templates as $template) {
            $exists = static::where('from', $template->from)
                ->where('to', $template->to)
                ->where('bus_unit_id', $template->bus_unit_id)
                ->whereDate('day', $date->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            // Defensive — store()/update() already block saving two
            // trips that would share a bus on an overlapping day, but
            // this runs unattended, so a template saved before that
            // check existed shouldn't silently double-book a bus here.
            if ($template->bus_unit_id !== null) {
                $conflict = static::busUnitConflict($template->from, $template->to, $template->bus_unit_id, $date->toDateString(), null);
                if ($conflict) {
                    Log::warning('Skipped activating recurring trip — bus unit already committed to another trip the same day.', [
                        'template_id' => $template->id,
                        'conflicting_trip_id' => $conflict->id,
                        'date' => $date->toDateString(),
                    ]);

                    continue;
                }
            }

            $instance = static::create([
                'from' => $template->from,
                'to' => $template->to,
                'day' => $date->toDateString(),
                'recurring_weekdays' => null,
                'return_date' => null,
                'departure_time' => $template->departure_time,
                'available_seats' => $template->bus_unit_id
                    ? BusUnit::find($template->bus_unit_id)->bookableSeatsCount()
                    : $template->available_seats,
                'bus_unit_id' => $template->bus_unit_id,
                'is_active' => true,
                'featured' => $template->featured,
                'sort_order' => $template->sort_order,
                'image' => $template->image,
                'boarding_outbound_legend' => $template->boarding_outbound_legend,
                'boarding_return_legend' => $template->boarding_return_legend,
            ]);

            foreach ($template->prices as $price) {
                $instance->prices()->create([
                    'trip_type' => $price->trip_type,
                    'price' => $price->price,
                    'is_active' => $price->is_active,
                ]);
            }

            TripGuide::linkTrip($instance);
            StandingReservation::applyToTrip($instance);

            $activated->push($instance);
        }

        return $activated;
    }

    /**
     * Whether this trip's travel date is already in the past — the
     * return date if there is one (the trip isn't "over" until the
     * return leg happened), otherwise the departure date. A trip with
     * no date at all ("Sin fecha") never closes on its own; an admin
     * has to deactivate it manually via is_active.
     */
    public function hasEnded(): bool
    {
        $lastDate = $this->return_date ?? $this->day;

        return $lastDate !== null && $lastDate->lt(today());
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * Active prices keyed by trip_type for quick lookup in the
     * seat-picker (one total per type, multiplied by the seat count).
     * Inactive or missing prices are simply absent from the map.
     *
     * @return Collection<string, TripTicketPrice>
     */
    public function activePrices(): Collection
    {
        return $this->prices
            ->where('is_active', true)
            ->keyBy('trip_type');
    }

    public function priceFor(string $tripType): ?TripTicketPrice
    {
        // Every trip prices itself — no global fallback (a shared
        // default masked a wrong price on routes that never got their
        // own). null means "not configured yet"; callers already show
        // "sin precio" for that case.
        return $this->activePrices()->get($tripType);
    }

    /**
     * Boarding-point instructions for this trip, set when it's created
     * (or later from Editar viaje) — no shared global to fall back on,
     * same reasoning as priceFor(): a shared default sent the wrong
     * meeting point on routes that never got their own set. Empty
     * string when this trip never got one; boardingLegendLines() skips
     * the line rather than show something wrong.
     */
    public function outboundMeetingPoint(): string
    {
        return (string) $this->boarding_outbound_legend;
    }

    public function returnMeetingPoint(): string
    {
        return (string) $this->boarding_return_legend;
    }

    public function formattedPriceFor(string $tripType): ?string
    {
        $price = $this->priceFor($tripType);
        if (! $price) return null;

        $value = '$'.number_format($price->price, 2);
        return Str::startsWith($value, '$') ? $value : '$'.$value;
    }

    /**
     * Backwards-compatible single-price accessor — returns whichever
     * one-way price is on file, or null. Used by views that haven't
     * been migrated to the two-price layout yet.
     */
    public function getFormattedPriceAttribute(): ?string
    {
        return $this->formattedPriceFor(TripTicketPrice::TYPE_ONE_WAY);
    }

    public function getNumericPriceAttribute(): float
    {
        return (float) ($this->priceFor(TripTicketPrice::TYPE_ONE_WAY)?->price ?? 0);
    }

    public function numericPriceFor(string $tripType): float
    {
        return (float) ($this->priceFor($tripType)?->price ?? 0);
    }

    /**
     * What ONE seat costs for this trip type — the value stored as a
     * reservation's unit_price. Same as numericPriceFor() except for
     * "especial", whose configured price is for a whole mancuerna
     * (e.g. $1,800 for seats 1 y 2 together): an apartado of
     * $seatCount especial seats is charged one mancuerna per started
     * pair — 1 or 2 seats = $1,800, 3 or 4 = $3,600 — split evenly so
     * the seats add back up to that total.
     */
    public function seatPriceFor(string $tripType, int $seatCount = TripTicketPrice::SEATS_PER_MANCUERNA): float
    {
        $price = $this->numericPriceFor($tripType);

        if ($tripType !== TripTicketPrice::TYPE_ESPECIAL) {
            return $price;
        }

        $seatCount = max(1, $seatCount);
        $mancuernas = (int) ceil($seatCount / TripTicketPrice::SEATS_PER_MANCUERNA);

        return round($price * $mancuernas / $seatCount, 2);
    }

    /**
     * Whether the given type currently has a per-trip OVERRIDE
     * (distinct from inheriting the global default). Used by the
     * admin "Precios de boleto" screen to render "(default)" hints
     * next to cells that don't have their own row.
     */
    public function hasOverrideFor(string $tripType): bool
    {
        return $this->prices()->where('trip_type', $tripType)->exists();
    }
}
