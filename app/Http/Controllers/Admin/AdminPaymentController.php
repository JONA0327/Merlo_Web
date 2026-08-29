<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatReservation;
use App\Services\OpenPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = SeatReservation::query()
            ->with(['landingRoute', 'seat', 'user'])
            ->whereNotNull('payment_method');

        if ($status = $request->query('status')) {
            $query->where('payment_status', $status);
        }
        if ($method = $request->query('method')) {
            $query->where('payment_method', $method);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('openpay_charge_id', 'like', "%{$search}%")
                    ->orWhere('ticket_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $payments = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $totals = [
            'completed' => (clone $query)->where('payment_status', 'completed')->sum('total'),
            'pending' => (clone $query)->where('payment_status', 'pending')->sum('total'),
            'refunded' => (clone $query)->where('payment_status', 'refunded')->sum('refund_amount'),
        ];

        return view('admin.pagos.index', [
            'payments' => $payments,
            'totals' => $totals,
            'filters' => [
                'status' => $status,
                'method' => $method,
                'q' => $search,
            ],
        ]);
    }

    public function show(SeatReservation $reservation): View
    {
        $reservation->load(['landingRoute.busUnit', 'seat', 'user', 'outboundVerifiedBy', 'returnVerifiedBy']);
        return view('admin.pagos.show', ['reservation' => $reservation]);
    }

    public function refund(Request $request, SeatReservation $reservation, OpenPayService $openpay): RedirectResponse
    {
        if (! $reservation->openpay_charge_id) {
            return back()->with('error', 'Esta reservación no tiene un cargo de OpenPay asociado.');
        }
        if (! $reservation->isPaymentCompleted()) {
            return back()->with('error', 'Solo se pueden reembolsar cargos completados.');
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) ($validated['amount'] ?? $reservation->total);
        if ($amount > (float) $reservation->total) {
            return back()->with('error', 'El reembolso no puede ser mayor al cargo original.');
        }

        try {
            $refund = $openpay->refund($reservation, $amount, $validated['reason'] ?? null);
        } catch (\Throwable $e) {
            Log::error('OpenPay refund failed: '.$e->getMessage(), [
                'reservation_id' => $reservation->id,
                'charge_id' => $reservation->openpay_charge_id,
            ]);
            return back()->with('error', 'No se pudo procesar el reembolso con OpenPay: '.$e->getMessage());
        }

        $reservation->update([
            'payment_status' => SeatReservation::PAYMENT_REFUNDED,
            'refunded_at' => now(),
            'refund_amount' => $amount,
            'refund_reason' => $validated['reason'] ?? null,
            'openpay_raw_response' => json_encode(array_merge(
                json_decode($reservation->openpay_raw_response ?? '{}', true) ?: [],
                ['refund' => $refund]
            )),
        ]);

        return back()->with('status', 'Reembolso procesado correctamente.');
    }
}
