<?php

namespace App\Http\Controllers;

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatHold;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Services\OpenPayService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SeatPickerController extends Controller
{
    public function show(LandingRoute $landingRoute, Request $request): View
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $landingRoute->load('busUnit.seats', 'prices');

        $requested = $request->query('type');
        $defaultTripType = in_array($requested, [TripTicketPrice::TYPE_ONE_WAY, TripTicketPrice::TYPE_ROUND_TRIP], true)
            ? $requested
            : $this->defaultTripTypeFor($landingRoute);

        $user = Auth::user();
        $savedCards = $user
            ? $user->savedCards()->orderByDesc('is_default')->orderByDesc('id')->get()
            : collect();

        return view('seat-picker', [
            'trip' => $landingRoute,
            'defaultTripType' => $defaultTripType,
            'takenIds' => $landingRoute->seatReservations()->pluck('bus_unit_seat_id'),
            'heldSeats' => $landingRoute->seatHolds()->active()->get(['bus_unit_seat_id', 'user_id', 'expires_at']),
            'savedCards' => $savedCards,
        ]);
    }

    public function store(Request $request, LandingRoute $landingRoute, OpenPayService $openpay): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $validated = $request->validate([
            'trip_type' => ['required', 'string', 'in:one_way,round_trip'],
            'payment_method' => ['required', 'string', 'in:card,oxxo,spei'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                'exists:bus_unit_seats,id',
            ],
            'openpay_token' => ['nullable', 'string'],
            'device_session_id' => ['nullable', 'string'],
            'saved_card_id' => ['nullable', 'integer', 'exists:saved_cards,id'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'array'],
            'save_card' => ['nullable', 'boolean'],
        ]);

        $tripType = $validated['trip_type'];
        $paymentMethod = $validated['payment_method'];
        $unitPrice = (float) ($landingRoute->priceFor($tripType)?->price ?? 0);
        abort_if($unitPrice <= 0, 422, 'Este tipo de boleto no está disponible para este viaje.');

        $seatIds = collect($validated['seat_ids'])->unique()->values();
        $user = $request->user();

        $requestedSeats = $landingRoute->busUnit->seats()->whereKey($seatIds)->get();
        abort_if($requestedSeats->count() !== $seatIds->count(), 422, 'Uno de los asientos seleccionados no pertenece a esta unidad.');
        abort_if($requestedSeats->contains(fn (BusUnitSeat $s) => ! $s->isBookable()), 422, 'Uno de los asientos no está disponible.');
        abort_if($requestedSeats->contains(fn (BusUnitSeat $s) => ! $s->allowsTripType($tripType)), 422, 'Uno de los asientos no está disponible para este tipo de viaje.');

        // Break down the price: the price in trip_ticket_prices is
        // the TOTAL shown to the customer (already includes 16% IVA
        // per the admin's decision), so we derive subtotal and tax.
        $total = round($unitPrice * $seatIds->count(), 2);
        $subtotal = round($total / 1.16, 2);
        $tax = round($total - $subtotal, 2);

        try {
            DB::transaction(function () use ($landingRoute, $seatIds, $user) {
                $trip = LandingRoute::query()->lockForUpdate()->findOrFail($landingRoute->id);
                $already = $trip->seatReservations()->whereIn('bus_unit_seat_id', $seatIds)->exists();
                abort_if($already, 409, 'Uno de los asientos ya fue reservado.');

                $heldByOthers = SeatHold::where('landing_route_id', $trip->id)
                    ->whereIn('bus_unit_seat_id', $seatIds)
                    ->where('user_id', '!=', $user?->id)
                    ->where('expires_at', '>', now())
                    ->exists();
                abort_if($heldByOthers, 409, 'Uno de los asientos está siendo elegido por otra persona.');

                abort_if($seatIds->count() > $trip->available_seats, 422, 'No hay suficientes asientos disponibles.');
            });
        } catch (HttpException $e) {
            return redirect()->route('travel.seats', ['landingRoute' => $landingRoute->id, 'type' => $tripType])
                ->with('error', $e->getMessage() ?: 'No se pudo procesar la selección.');
        } catch (QueryException $e) {
            return redirect()->route('travel.seats', ['landingRoute' => $landingRoute->id, 'type' => $tripType])
                ->with('error', 'Conflicto de base de datos al reservar. Intenta de nuevo.');
        }

        // Create the reservation in pending_payment state so we
        // can attach the OpenPay charge id once we have it.
        $reservation = DB::transaction(function () use ($landingRoute, $user, $seatIds, $tripType, $paymentMethod, $subtotal, $tax, $total, $validated) {
            $first = $seatIds->first();
            $reservation = SeatReservation::create([
                'landing_route_id' => $landingRoute->id,
                'bus_unit_seat_id' => $first,
                'user_id' => $user?->id,
                'trip_type' => $tripType,
                'unit_price' => $subtotal + $tax,
                'payment_method' => $paymentMethod,
                'payment_status' => SeatReservation::PAYMENT_PENDING,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'MXN',
                'customer_name' => $user?->name,
                'customer_email' => $user?->email,
                'customer_phone' => $validated['customer_phone'] ?? $user?->phone,
                'billing_address' => $validated['billing_address'] ?? null,
                'ip_address' => request()->ip(),
                'device_fingerprint' => $validated['device_session_id'] ?? null,
            ]);

            // For multi-seat purchases we still create one
            // reservation row (for the first seat) but also link
            // the others as additional "child" reservations so the
            // seat picker knows they're all paid. Since our schema
            // only supports one seat per row, we use the same
            // order_id across multiple rows by linking them via
            // a unique note. For now, we replicate the reservation
            // for each seat to keep the existing data model intact.
            if ($seatIds->count() > 1) {
                foreach ($seatIds->slice(1) as $seatId) {
                    SeatReservation::create([
                        'landing_route_id' => $landingRoute->id,
                        'bus_unit_seat_id' => $seatId,
                        'user_id' => $user?->id,
                        'trip_type' => $tripType,
                        'unit_price' => $subtotal + $tax,
                        'payment_method' => $paymentMethod,
                        'payment_status' => SeatReservation::PAYMENT_PENDING,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $total,
                        'currency' => 'MXN',
                        'customer_name' => $user?->name,
                        'customer_email' => $user?->email,
                        'notes' => 'group:'.$reservation->id,
                    ]);
                }
            }

            return $reservation;
        });

        // Resolve the OpenPay "source" for card payments:
        //   - saved card (one-click): pass the saved card's openpay_card_id
        //   - new card: pass the token from OpenPay.js
        // For OXXO / SPEI, no source is needed.
        $openpaySource = null;
        $deviceData = [];
        $usingSavedCard = false;

        if ($paymentMethod === 'card') {
            if (! empty($validated['saved_card_id']) && $user) {
                $savedCard = $user->savedCards()->whereKey($validated['saved_card_id'])->first();
                if ($savedCard) {
                    $openpaySource = $savedCard->openpay_card_id;
                    $usingSavedCard = true;
                }
            }
            if (! $openpaySource) {
                $openpaySource = $validated['openpay_token'] ?? null;
                if (! $openpaySource) {
                    return redirect()->route('travel.seats', ['landingRoute' => $landingRoute->id, 'type' => $tripType])
                        ->with('error', 'Ingresa una tarjeta para continuar.');
                }
                $deviceData = ['device_session_id' => $validated['device_session_id'] ?? null];
            }
        }

        // Talk to OpenPay to create the charge. For card, we need
        // a token from OpenPay.js (or a saved card id). For OXXO /
        // SPEI, the source is null and OpenPay returns a barcode / CLABE.
        $customerId = $user
            ? $openpay->ensureCustomer($user, $validated['customer_phone'] ?? null)
            : null;

        try {
            $charge = $openpay->createCharge(
                $reservation,
                $customerId ?? 'guest-'.$reservation->id,
                $openpaySource,
                $deviceData
            );
        } catch (\Throwable $e) {
            Log::error('OpenPay createCharge failed: '.$e->getMessage(), [
                'reservation_id' => $reservation->id,
            ]);
            $reservation->update(['payment_status' => SeatReservation::PAYMENT_FAILED]);
            return redirect()->route('travel.payment.error', $reservation)
                ->with('error', 'No se pudo procesar el pago: '.$e->getMessage());
        }

        $reservation->update([
            'openpay_customer_id' => $customerId,
            'openpay_charge_id' => $charge['id'],
            'openpay_authorization' => $charge['authorization'],
            'openpay_payment_method' => $charge['method'],
            'openpay_card_brand' => $charge['card_brand'],
            'openpay_card_last4' => $charge['card_last4'],
            'openpay_card_exp_month' => $charge['card_exp_month'],
            'openpay_card_exp_year' => $charge['card_exp_year'],
            'openpay_fee' => $charge['fee'],
            'openpay_barcode_url' => $charge['barcode_url'],
            'openpay_barcode' => $charge['barcode'],
            'openpay_payment_url' => $charge['payment_url'],
            'openpay_expires_at' => $charge['expires_at'],
            'openpay_raw_response' => json_encode($charge['raw']),
            'payment_status' => $this->mapOpenpayStatus($charge['status']),
            'paid_at' => $charge['status'] === 'completed' ? now() : null,
        ]);

        // Optional: save the card for future one-click checkouts. Only
        // applies to new-card payments (saved-card payments don't have
        // a fresh token to store).
        if ($paymentMethod === 'card'
            && ! $usingSavedCard
            && $request->boolean('save_card')
            && $user
            && $charge['status'] === 'completed'
            && ! empty($validated['openpay_token'])) {
            try {
                $openpay->saveCard(
                    $user,
                    $validated['device_session_id'] ?? '',
                    $validated['openpay_token'],
                    $request->boolean('make_default')
                );
            } catch (\Throwable $e) {
                Log::warning('OpenPay saveCard failed (charge still succeeded): '.$e->getMessage());
            }
        }

        // Decrement available_seats on successful card charge
        // (or for pending OXXO / SPEI — they're held for the
        // buyer until they pay or it expires).
        $landingRoute->decrement('available_seats', $seatIds->count());

        // Release any holds this user had on these seats.
        SeatHold::where('landing_route_id', $landingRoute->id)->whereIn('bus_unit_seat_id', $seatIds)->delete();

        SeatAvailabilityUpdated::dispatchSafely(
            $landingRoute->id,
            $seatIds->map(fn ($id) => ['id' => (int) $id, 'status' => 'purchased'])->all()
        );

        if ($charge['status'] === 'completed') {
            $reservation->sendGroupTickets();
        }

        return match ($charge['status']) {
            'completed' => redirect()->route('travel.payment.success', $reservation),
            default => redirect()->route('travel.payment.pending', $reservation),
        };
    }

    public function success(SeatReservation $reservation): View
    {
        return view('payment.success', ['reservation' => $reservation->load('landingRoute.busUnit', 'seat')]);
    }

    public function pending(SeatReservation $reservation): View
    {
        return view('payment.pending', ['reservation' => $reservation->load('landingRoute.busUnit', 'seat')]);
    }

    public function error(SeatReservation $reservation): View
    {
        return view('payment.error', ['reservation' => $reservation->load('landingRoute.busUnit', 'seat')]);
    }

    private function defaultTripTypeFor(LandingRoute $trip): string
    {
        $prices = $trip->activePrices();
        if ($prices->isEmpty()) return TripTicketPrice::TYPE_ONE_WAY;
        $cheapest = $prices->sortBy('price')->keys()->first();
        return $cheapest ?: TripTicketPrice::TYPE_ONE_WAY;
    }

    private function mapOpenpayStatus(?string $status): string
    {
        return match ($status) {
            'completed' => SeatReservation::PAYMENT_COMPLETED,
            'failed', 'cancelled' => SeatReservation::PAYMENT_FAILED,
            'refunded' => SeatReservation::PAYMENT_REFUNDED,
            'chargeback' => SeatReservation::PAYMENT_CHARGEBACK,
            default => SeatReservation::PAYMENT_PENDING,
        };
    }
}
