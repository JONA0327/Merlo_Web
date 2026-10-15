<?php

namespace App\Services;

use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripCorte;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Desglose de asientos/dinero de un viaje y su envío a Control Reportes.
 *
 * Cuenta solo la ida (la vuelta es otro boleto sobre el mismo viaje) y
 * descarta cancelados, fallidos, reembolsados y vueltas anuladas.
 * "Vendido" = pago completado; "apartado" = pendiente (puede traer abono).
 * "Después" = vendido el día del viaje o más tarde (asientos que sobraron).
 */
class TripCorteService
{
    /**
     * $externalSeats/$externalAmount: ventas fuera del sistema; si no se pasan, se usan las del corte ya guardado.
     */
    public function summarize(LandingRoute $trip, ?int $externalSeats = null, ?float $externalAmount = null): array
    {
        $saved = TripCorte::where('landing_route_id', $trip->id)->first();
        $externalSeats ??= (int) ($saved->external_seats ?? 0);
        $externalAmount ??= (float) ($saved->external_amount ?? 0);

        $rows = $trip->seatReservations()
            ->where('leg', SeatReservation::LEG_OUTBOUND)
            ->where('status', '!=', SeatReservation::STATUS_CANCELLED)
            ->whereIn('payment_status', [SeatReservation::PAYMENT_COMPLETED, SeatReservation::PAYMENT_PENDING])
            ->whereNull('return_voided_at')
            ->get(['unit_price', 'abono', 'payment_status', 'created_at']);

        $sold = $rows->where('payment_status', SeatReservation::PAYMENT_COMPLETED);
        $apartados = $rows->where('payment_status', SeatReservation::PAYMENT_PENDING);
        $late = $sold->filter(fn ($r) => $trip->day && $r->created_at->toDateString() >= $trip->day->toDateString());
        $total = $trip->busUnit?->bookableSeatsCount() ?? 0;

        return [
            'fecha' => $trip->day?->toDateString(),
            'total_seats' => $total,
            'sold_seats' => $sold->count(),
            'late_sold_seats' => $late->count(),
            'apartado_seats' => $apartados->count(),
            'free_seats' => max(0, $total - $rows->count() - $externalSeats),
            'external_seats' => $externalSeats,
            'external_amount' => round($externalAmount, 2),
            'sold_amount' => round($sold->sum('unit_price'), 2),
            'late_amount' => round($late->sum('unit_price'), 2),
            'abonos_amount' => round($apartados->sum('abono'), 2),
            'total_cost' => round($rows->sum('unit_price') + $externalAmount, 2),
        ];
    }

    /** Guarda el corte y lo manda a Control. Si el envío falla, la transacción se revierte. */
    public function confirm(LandingRoute $trip, ?int $contratoId, ?int $userId, int $externalSeats = 0, float $externalAmount = 0): TripCorte
    {
        $data = $this->summarize($trip, $externalSeats, $externalAmount);

        $libres = $data['total_seats'] - ($data['sold_seats'] + $data['apartado_seats']);
        if ($externalSeats > $libres) {
            throw new RuntimeException("Solo hay {$libres} asientos libres: no puedes registrar {$externalSeats} vendidos fuera del sistema.");
        }

        if ($data['fecha'] === null) {
            throw new RuntimeException('El viaje no tiene fecha.');
        }

        if (! $contratoId) {
            throw new RuntimeException('Selecciona el contrato de compras al que se liga el viaje.');
        }

        return DB::transaction(function () use ($trip, $data, $contratoId, $userId) {
            $corte = TripCorte::updateOrCreate(
                ['landing_route_id' => $trip->id],
                $data + ['contrato_id' => $contratoId, 'created_by' => $userId],
            );

            $this->http()->post('/merlo/cortes', $data + [
                'trip_id' => $trip->id,
                'ruta' => "{$trip->from} → {$trip->to}",
                'contrato_id' => $contratoId,
            ])->throw();

            $corte->update(['sent_at' => now()]);

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
