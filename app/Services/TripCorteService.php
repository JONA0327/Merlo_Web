<?php

namespace App\Services;

use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripCorte;
use App\Models\TripCorteLine;
use App\Models\TripTicketPrice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Corte de un viaje: desglose de asientos/dinero, otros conceptos del día (gastos e
 * ingresos) y envío a Control Reportes.
 *
 * Cuenta la ida y los "solo regreso" (el regreso que acompaña a una ida/redondo ya está
 * cobrado en la ida) y descarta cancelados, fallidos, reembolsados y vueltas anuladas.
 * "Vendido" incluye pagados y apartados, cada asiento a su precio por viaje.
 * "Después" = vendido el día del viaje o más tarde (asientos que sobraron).
 */
class TripCorteService
{
    /** Forma de pago de los asientos ya pagados (lo demás: "Por definir"). */
    private const METHOD_LABELS = [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'spei' => 'Transferencia',
        'card' => 'Tarjeta / OXXO',
        'oxxo' => 'Tarjeta / OXXO',
    ];

    private const COLUMNS = [
        'fecha', 'total_seats', 'sold_seats', 'late_sold_seats', 'apartado_seats', 'free_seats',
        'external_seats', 'external_amount', 'sold_amount', 'late_amount', 'abonos_amount', 'total_cost',
    ];

    /**
     * $externalSeats/$externalAmount: vendido sin apartar en guía; si no se pasan, se usan los del corte guardado.
     */
    public function summarize(LandingRoute $trip, ?int $externalSeats = null, ?float $externalAmount = null): array
    {
        $saved = TripCorte::where('landing_route_id', $trip->id)->first();
        $externalSeats ??= (int) ($saved->external_seats ?? 0);
        $externalAmount ??= (float) ($saved->external_amount ?? 0);

        $rows = $trip->seatReservations()
            ->where(fn ($q) => $q->where('leg', SeatReservation::LEG_OUTBOUND)->orWhere('trip_type', TripTicketPrice::TYPE_REGRESO))
            ->where('status', '!=', SeatReservation::STATUS_CANCELLED)
            ->whereIn('payment_status', [SeatReservation::PAYMENT_COMPLETED, SeatReservation::PAYMENT_PENDING])
            ->whereNull('return_voided_at')
            ->get(['unit_price', 'abono', 'payment_status', 'payment_method', 'created_at', 'trip_type']);

        // Precio del asiento: el del renglón; si quedó en 0 (por definir), el precio del viaje para su categoría.
        $price = fn ($r) => (float) $r->unit_price > 0 ? (float) $r->unit_price : $trip->seatPriceFor($r->trip_type);

        $paid = $rows->where('payment_status', SeatReservation::PAYMENT_COMPLETED);
        $apartados = $rows->where('payment_status', SeatReservation::PAYMENT_PENDING);
        $late = $rows->filter(fn ($r) => $trip->day && $r->created_at->toDateString() >= $trip->day->toDateString());
        $total = $trip->busUnit?->bookableSeatsCount() ?? 0;
        $typeLabels = TripTicketPrice::tripTypes();

        return [
            'fecha' => $trip->day?->toDateString(),
            'total_seats' => $total,
            'sold_seats' => $rows->count(),
            'late_sold_seats' => $late->count(),
            'apartado_seats' => $apartados->count(),
            'free_seats' => max(0, $total - $rows->count() - $externalSeats),
            'external_seats' => $externalSeats,
            'external_amount' => round($externalAmount, 2),
            'sold_amount' => round($rows->sum($price), 2),
            'late_amount' => round($late->sum($price), 2),
            // Parte de lo vendido que ya se cobró como abono (no se suma otra vez).
            'abonos_amount' => round($apartados->sum('abono'), 2),
            'total_cost' => round($rows->sum($price) + $externalAmount, 2),

            // Desglose informativo (hoja del día)
            'by_type' => $rows->groupBy('trip_type')
                ->map(fn ($g, $t) => ['label' => $typeLabels[$t] ?? $t, 'seats' => $g->count(), 'amount' => round($g->sum($price), 2)])
                ->values()->all(),
            'by_method' => $paid->groupBy(fn ($r) => self::METHOD_LABELS[$r->payment_method] ?? 'Por definir')
                ->map(fn ($g, $label) => ['label' => $label, 'seats' => $g->count(), 'amount' => round($g->sum($price), 2)])
                ->values()->all(),
            'por_cobrar_seats' => $apartados->count(),
            'por_cobrar_amount' => round($apartados->sum(fn ($r) => max(0, $price($r) - (float) $r->abono)), 2),
        ];
    }

    /** Otros conceptos del día (gastos e ingresos) capturados para este viaje. */
    public function lines(LandingRoute $trip): Collection
    {
        return TripCorteLine::where('landing_route_id', $trip->id)->orderBy('id')->get();
    }

    /** Huella de lo que se envía a Control (renglones, efectivo y vendido sin apartar): detecta cambios sin reenviar. */
    public function signature(Collection $lines, ?TripCorte $corte): string
    {
        return md5(json_encode([
            $lines->map(fn ($l) => [$l->kind, $l->concept, (float) $l->quantity, (float) $l->unit_amount, $l->note, (bool) $l->counts])->all(),
            $corte?->cash_amount !== null ? (float) $corte->cash_amount : null,
            (int) ($corte->external_seats ?? 0),
            (float) ($corte->external_amount ?? 0),
        ]));
    }

    /** Guarda los datos del corte (vendido sin apartar, efectivo, contrato) sin enviarlo todavía. */
    public function save(LandingRoute $trip, ?int $contratoId, ?int $userId, int $externalSeats = 0, float $externalAmount = 0, ?float $cash = null): TripCorte
    {
        $data = $this->summarize($trip, $externalSeats, $externalAmount);

        if ($data['fecha'] === null) {
            throw new RuntimeException('El viaje no tiene fecha.');
        }

        $libres = $data['total_seats'] - $data['sold_seats'];
        if ($externalSeats > $libres) {
            throw new RuntimeException("Solo hay {$libres} asientos libres: no puedes registrar {$externalSeats} vendidos sin apartar en guía.");
        }

        return TripCorte::updateOrCreate(
            ['landing_route_id' => $trip->id],
            Arr::only($data, self::COLUMNS) + ['cash_amount' => $cash] + array_filter(['contrato_id' => $contratoId, 'created_by' => $userId], fn ($v) => $v !== null),
        );
    }

    /** Guarda el corte y lo manda a Control. Si el envío falla, la transacción se revierte. */
    public function confirm(LandingRoute $trip, ?int $contratoId, ?int $userId, int $externalSeats = 0, float $externalAmount = 0, ?float $cash = null): TripCorte
    {
        if (! $contratoId) {
            throw new RuntimeException('Selecciona el contrato de compras al que se liga el viaje.');
        }

        return DB::transaction(function () use ($trip, $contratoId, $userId, $externalSeats, $externalAmount, $cash) {
            $corte = $this->save($trip, $contratoId, $userId, $externalSeats, $externalAmount, $cash);
            $data = $this->summarize($trip);
            $lines = $this->lines($trip);

            $this->http()->post('/merlo/cortes', Arr::only($data, self::COLUMNS) + [
                'trip_id' => $trip->id,
                'ruta' => "{$trip->from} → {$trip->to}",
                'contrato_id' => $contratoId,
                'efectivo' => $cash,
                'desglose' => Arr::only($data, ['by_type', 'by_method', 'abonos_amount', 'por_cobrar_seats', 'por_cobrar_amount']),
                'lines' => $lines->map(fn ($l) => [
                    'id' => $l->id, 'kind' => $l->kind, 'concept' => $l->concept, 'quantity' => (float) $l->quantity,
                    'unit_amount' => (float) $l->unit_amount, 'amount' => (float) $l->amount, 'note' => $l->note, 'counts' => (bool) $l->counts,
                ])->values()->all(),
            ])->throw();

            $corte->update(['sent_at' => now(), 'lines_signature' => $this->signature($lines, $corte->refresh())]);

            return $corte;
        });
    }

    /** Contratos tipo "compras" de Control, para ligar el viaje. [] si Control no responde. */
    public function contratosCompras(): array
    {
        try {
            return $this->http()->get('/merlo/contratos-compras')->throw()->json('data', []);
        } catch (\Throwable) {
            return [];
        }
    }

    public function http(): PendingRequest
    {
        $url = config('services.control_reportes.url');
        $token = config('services.control_reportes.token');
        if (! $url || ! $token) {
            throw new RuntimeException('Falta CONTROL_REPORTES_URL / CONTROL_REPORTES_TOKEN en .env.');
        }

        return Http::baseUrl(rtrim($url, '/').'/api')->withToken($token)->acceptJson()->timeout(10);
    }
}
