<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatReservation;
use App\Services\OpenPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OpenPay webhooks. OpenPay POSTs here when asynchronous events happen
 * (OXXO barcode paid at a store, SPEI transfer received, chargeback
 * initiated, etc.). Card charges are typically completed synchronously
 * so the webhook mostly covers OXXO/SPEI confirmation.
 *
 * The endpoint is excluded from CSRF in bootstrap/app.php since
 * OpenPay can't supply a token. We still verify the payload's shape
 * (see OpenPayService::verifyWebhook) before touching the database.
 */
class OpenPayWebhookController extends Controller
{
    public function handle(Request $request, OpenPayService $openpay): JsonResponse
    {
        $payload = $request->all();

        if (! $openpay->verifyWebhook($payload)) {
            Log::warning('OpenPay webhook rejected (failed verification)', ['keys' => array_keys($payload)]);
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $chargeId = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;
        $status = $payload['transaction_status'] ?? $payload['status'] ?? null;

        if (! $chargeId) {
            return response()->json(['error' => 'missing charge id'], 400);
        }

        // OXXO charges have a barcode + due_date in the nested
        // payment_method object, but the webhook is mostly informative
        // — the "paid" event is what we care about.
        $reservation = SeatReservation::query()
            ->where('openpay_charge_id', $chargeId)
            ->first();

        if (! $reservation) {
            // OpenPay sometimes retries; returning 200 stops the retry
            // when the charge truly belongs to a different system.
            Log::info('OpenPay webhook for unknown charge id', ['charge_id' => $chargeId]);
            return response()->json(['ok' => true, 'unknown' => true]);
        }

        $normalized = match (true) {
            $type === 'charge.succeeded' || $status === 'completed' || $status === 'paid' => SeatReservation::PAYMENT_COMPLETED,
            $type === 'charge.failed' || $status === 'failed' => SeatReservation::PAYMENT_FAILED,
            $type === 'charge.cancelled' || $status === 'cancelled' => SeatReservation::PAYMENT_FAILED,
            $type === 'charge.refunded' || $status === 'refunded' => SeatReservation::PAYMENT_REFUNDED,
            $type === 'charge.chargeback.created' || $status === 'chargeback' => SeatReservation::PAYMENT_CHARGEBACK,
            default => null,
        };

        if ($normalized === null) {
            // Unknown event type — return 200 so OpenPay doesn't retry,
            // but log it so we can extend the matcher if needed.
            Log::info('OpenPay webhook with unmapped event', [
                'charge_id' => $chargeId,
                'type' => $type,
                'status' => $status,
            ]);
            return response()->json(['ok' => true, 'unmapped' => true]);
        }

        $updates = ['payment_status' => $normalized];
        if ($normalized === SeatReservation::PAYMENT_COMPLETED && ! $reservation->paid_at) {
            $updates['paid_at'] = now();
        }
        if ($normalized === SeatReservation::PAYMENT_REFUNDED && ! $reservation->refunded_at) {
            $updates['refunded_at'] = now();
            $updates['refund_amount'] = $reservation->total;
        }
        if ($normalized === SeatReservation::PAYMENT_CHARGEBACK && ! $reservation->chargeback_at) {
            $updates['chargeback_at'] = now();
        }

        $reservation->update($updates);

        if ($normalized === SeatReservation::PAYMENT_COMPLETED) {
            $reservation->sendGroupTickets();
        }

        return response()->json(['ok' => true]);
    }
}
