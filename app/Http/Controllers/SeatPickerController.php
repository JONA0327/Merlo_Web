<?php

namespace App\Http\Controllers;

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\PaymentMethod;
use App\Models\SeatHold;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Services\EvolutionWhatsAppService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SeatPickerController extends Controller
{
    public function show(LandingRoute $landingRoute, Request $request): View
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $landingRoute->load('busUnit.seats', 'prices');

        // Round-trip seats whose return leg an admin released for resale
        // and whose resale window is still open (not yet bought by
        // someone else). Subject to availability, as requested: this is
        // the ONLY inventory the "Regreso disponible" mode can sell —
        // it never touches the normal ida/redondo seat pool.
        $resaleSeatIds = $landingRoute->seatReservations()
            ->where('trip_type', TripTicketPrice::TYPE_ROUND_TRIP)
            ->whereNotNull('return_released_at')
            ->whereNull('resold_return_reservation_id')
            ->where('return_resale_expires_at', '>', now())
            ->pluck('bus_unit_seat_id');

        $requested = $request->query('type');
        $validTypes = [TripTicketPrice::TYPE_ONE_WAY, TripTicketPrice::TYPE_ROUND_TRIP];
        if ($resaleSeatIds->isNotEmpty()) {
            $validTypes[] = 'return_resale';
        }
        // "De regreso" (a direct, standalone return-leg purchase — not the
        // resale of an already-released round-trip return) only makes
        // sense when the trip actually has a return date and the admin
        // configured a price for it.
        if ($landingRoute->return_date !== null && $landingRoute->priceFor(TripTicketPrice::TYPE_REGRESO)) {
            $validTypes[] = TripTicketPrice::TYPE_REGRESO;
        }
        $defaultTripType = in_array($requested, $validTypes, true)
            ? $requested
            : $this->defaultTripTypeFor($landingRoute);

        $user = Auth::user();
        $savedCards = $user
            ? $user->savedCards()->orderByDesc('is_default')->orderByDesc('id')->get()
            : collect();

        // Holder key for the visitor: a logged-in account uses 'u:{id}'
        // and a guest uses 's:{session_id}' — both shapes match the
        // seat_holds.holder_id accessor used below, so the JS just
        // compares one string per hold.
        $holderKey = $user ? 'u:'.$user->id : 's:'.session()->getId();

        return view('seat-picker', [
            'trip' => $landingRoute,
            'defaultTripType' => $defaultTripType,
            'takenIds' => $landingRoute->seatReservations()->pluck('bus_unit_seat_id'),
            'heldSeats' => $landingRoute->seatHolds()->active()->get(['bus_unit_seat_id', 'user_id', 'session_id', 'expires_at'])->map(fn ($h) => [
                'bus_unit_seat_id' => $h->bus_unit_seat_id,
                'holder_id' => $h->holder_id,
                'expires_at' => $h->expires_at->toIso8601String(),
            ]),
            'selfHolderId' => $holderKey,
            'savedCards' => $savedCards,
            'resaleSeatIds' => $resaleSeatIds,
            'paymentMethods' => config('services.openpay.enabled') ? collect() : PaymentMethod::active()->get(),
        ]);
    }

    public function store(Request $request, LandingRoute $landingRoute, EvolutionWhatsAppService $whatsapp): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $validated = $request->validate([
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,return_resale,regreso'],
            // Only transfer + cash are wired up — card / OXXO / SPEI
            // went through OpenPay, which is currently disabled. The
            // SeatReservation::PAYMENT_METHOD_* constants still exist
            // for historical rows (older purchases show up with the
            // old method in admin / pagos) but no new ones can be created.
            'payment_method' => ['required', 'string', 'in:transfer,cash'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                'exists:bus_unit_seats,id',
            ],
            // Required only when there's no logged-in user (guest checkout):
            // a signed-in customer already has a name/email/phone on file.
            'customer_name' => [Rule::requiredIf(fn () => ! $request->user()), 'nullable', 'string', 'max:120'],
            'customer_phone' => [Rule::requiredIf(fn () => ! $request->user()), 'nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'array'],
        ]);

        $tripType = $validated['trip_type'];
        $isReturnResale = $tripType === 'return_resale';
        $paymentMethod = $validated['payment_method'];
        // A resold return leg is priced like a one-way ticket — there's
        // no separate trip_ticket_prices row for 'return_resale'.
        $unitPrice = (float) ($landingRoute->priceFor($isReturnResale ? TripTicketPrice::TYPE_ONE_WAY : $tripType)?->price ?? 0);
        abort_if($unitPrice <= 0, 422, 'Este tipo de boleto no está disponible para este viaje.');

        $seatIds = collect($validated['seat_ids'])->unique()->values();
        $user = $request->user();

        $requestedSeats = $landingRoute->busUnit->seats()->whereKey($seatIds)->get();
        abort_if($requestedSeats->count() !== $seatIds->count(), 422, 'Uno de los asientos seleccionados no pertenece a esta unidad.');
        abort_if($requestedSeats->contains(fn (BusUnitSeat $s) => ! $s->isBookable()), 422, 'Uno de los asientos no está disponible.');
        // The static allowed_trip_type restriction doesn't apply to
        // resale seats — eligibility there is decided entirely by
        // whether an admin released that specific reservation's return
        // leg (checked below), not by the seat's general configuration.
        if (! $isReturnResale) {
            abort_if($requestedSeats->contains(fn (BusUnitSeat $s) => ! $s->allowsTripType($tripType)), 422, 'Uno de los asientos no está disponible para este tipo de viaje.');
        }

        // Break down the price: the price in trip_ticket_prices is
        // the TOTAL shown to the customer (already includes 16% IVA
        // per the admin's decision), so we derive subtotal and tax.
        $total = round($unitPrice * $seatIds->count(), 2);
        $subtotal = round($total / 1.16, 2);
        $tax = round($total - $subtotal, 2);

        if ($isReturnResale) {
            // Resale inventory isn't the normal seat/hold pool — it's
            // exactly the set of round-trip reservations an admin
            // explicitly released. Lock those specific rows and check
            // (still, under the lock — someone else may have bought
            // one a moment ago) that every requested seat still has an
            // open window before creating anything.
            try {
                $reservation = DB::transaction(function () use ($landingRoute, $seatIds, $user, $paymentMethod, $subtotal, $tax, $total, $validated) {
                    $originals = SeatReservation::query()
                        ->where('landing_route_id', $landingRoute->id)
                        ->whereIn('bus_unit_seat_id', $seatIds)
                        ->where('trip_type', TripTicketPrice::TYPE_ROUND_TRIP)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('bus_unit_seat_id');

                    foreach ($seatIds as $seatId) {
                        $original = $originals->get($seatId);
                        abort_if(! $original || ! $original->isResaleWindowOpen(), 409, 'Uno de los asientos de regreso ya no está disponible.');
                    }

                    $reservation = null;
                    foreach ($seatIds as $seatId) {
                        $original = $originals->get($seatId);
                        $new = SeatReservation::create([
                            'landing_route_id' => $landingRoute->id,
                            'bus_unit_seat_id' => $seatId,
                            'user_id' => $user?->id,
                            'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
                            'leg' => SeatReservation::LEG_RETURN,
                            'source_reservation_id' => $original->id,
                            'unit_price' => $subtotal + $tax,
                            'payment_method' => $paymentMethod,
                            'payment_status' => SeatReservation::PAYMENT_PENDING,
                            'subtotal' => $subtotal,
                            'tax' => $tax,
                            'total' => $total,
                            'currency' => 'MXN',
                            'customer_name' => $validated['customer_name'] ?? $user?->name,
                            'customer_email' => $user?->email,
                            'customer_phone' => $validated['customer_phone'] ?? $user?->phone,
                            'billing_address' => $validated['billing_address'] ?? null,
                            'ip_address' => request()->ip(),
                            'device_fingerprint' => $validated['device_session_id'] ?? null,
                            'notes' => $reservation ? 'group:'.$reservation->id : null,
                        ]);
                        $original->update(['resold_return_reservation_id' => $new->id]);
                        $reservation ??= $new;
                    }

                    return $reservation;
                });
            } catch (HttpException $e) {
                return redirect()->route('travel.seats', ['landingRoute' => $landingRoute->id, 'type' => $tripType])
                    ->with('error', $e->getMessage() ?: 'No se pudo procesar la selección.');
            } catch (QueryException $e) {
                return redirect()->route('travel.seats', ['landingRoute' => $landingRoute->id, 'type' => $tripType])
                    ->with('error', 'Conflicto de base de datos al reservar. Intenta de nuevo.');
            }
        } else {
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
            $leg = $tripType === TripTicketPrice::TYPE_REGRESO ? SeatReservation::LEG_RETURN : SeatReservation::LEG_OUTBOUND;

            $reservation = DB::transaction(function () use ($landingRoute, $user, $seatIds, $tripType, $leg, $paymentMethod, $subtotal, $tax, $total, $validated) {
                $first = $seatIds->first();
                $reservation = SeatReservation::create([
                    'landing_route_id' => $landingRoute->id,
                    'bus_unit_seat_id' => $first,
                    'user_id' => $user?->id,
                    'trip_type' => $tripType,
                    'leg' => $leg,
                    'unit_price' => $subtotal + $tax,
                    'payment_method' => $paymentMethod,
                    'payment_status' => SeatReservation::PAYMENT_PENDING,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'currency' => 'MXN',
                    'customer_name' => $validated['customer_name'] ?? $user?->name,
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
                            'leg' => $leg,
                            'unit_price' => $subtotal + $tax,
                            'payment_method' => $paymentMethod,
                            'payment_status' => SeatReservation::PAYMENT_PENDING,
                            'subtotal' => $subtotal,
                            'tax' => $tax,
                            'total' => $total,
                            'currency' => 'MXN',
                            'customer_name' => $validated['customer_name'] ?? $user?->name,
                            'customer_email' => $user?->email,
                            'notes' => 'group:'.$reservation->id,
                        ]);
                    }
                }

                return $reservation;
            });
        }

        if ($paymentMethod === SeatReservation::PAYMENT_METHOD_TRANSFER) {
            // Manual bank-transfer flow: no gateway call at all. The seat
            // is held (via the shared decrement/hold-release tail below)
            // for 3 days while an admin matches the reference against a
            // real transfer on their bank statement — see
            // SeatReservation::generateTransferReference(). Falls back
            // to the form-submitted name when the buyer is a guest
            // (no $user), so the reference initials still work.
            $reservation->update([
                'transfer_reference' => SeatReservation::generateTransferReference(
                    $landingRoute->day,
                    $seatIds->count(),
                    $validated['customer_name'] ?? $user?->name ?? 'Cliente'
                ),
                'transfer_expires_at' => now()->addDays(3),
                'payment_status' => SeatReservation::PAYMENT_PENDING,
            ]);
            $chargeStatus = 'pending';
        } elseif ($paymentMethod === SeatReservation::PAYMENT_METHOD_CASH) {
            // Cash-at-the-window flow: no gateway, no reference, no
            // automatic expiry. The seat is reserved (still held via the
            // shared decrement/hold-release tail below) until an admin
            // physically confirms the cash was received — then the QR
            // is generated and printed at the counter (see
            // AdminPaymentController::confirmCash / cashTicket). Sending
            // any "thanks for paying" email here would be wrong: the
            // ticket literally doesn't exist yet.
            $reservation->update([
                'payment_status' => SeatReservation::PAYMENT_PENDING,
            ]);
            $chargeStatus = 'pending';
        }

        // Decrement available_seats regardless of payment method —
        // every accepted checkout (transfer / cash) holds the seat for
        // the buyer until they pay or the window lapses. Not for resold
        // return legs — that capacity was already decremented when the
        // original round-trip reservation was purchased.
        if (! $isReturnResale) {
            $landingRoute->decrement('available_seats', $seatIds->count());
        }

        // Release any holds this user had on these seats.
        SeatHold::where('landing_route_id', $landingRoute->id)->whereIn('bus_unit_seat_id', $seatIds)->delete();

        SeatAvailabilityUpdated::dispatchSafely(
            $landingRoute->id,
            $seatIds->map(fn ($id) => ['id' => (int) $id, 'status' => 'purchased'])->all()
        );

        // Neither method produces a QR-bearing ticket yet — that only
        // happens once an admin confirms the transfer/cash in /admin/pagos
        // (see AdminPaymentController::validateTransfer()/confirmCash()).
        // Right now the buyer just gets a plain-text "your seat is
        // reserved, pay X to confirm" notice, same as the admin-apartado
        // cash-pending flow — covers every seat in the group, not just
        // the root row.
        if ($reservation->customer_phone && $whatsapp->isConfigured()) {
            try {
                $whatsapp->sendReservationNotice($reservation->groupMembers());
            } catch (\Throwable $e) {
                Log::warning('Online-purchase WhatsApp reservation notice failed for reservation '.$reservation->id.': '.$e->getMessage());
            }
        }

        // No payment method in this build produces an instant "completed"
        // (every accepted checkout waits for an admin to validate the
        // cash receipt or transfer reference) — so we land on pending
        // every time. Kept as a single redirect rather than a match() so
        // it's obvious there's only one outcome.
        return redirect()->route('travel.payment.pending', $reservation);
    }

    public function uploadTransferProof(Request $request, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->user_id === $request->user()?->id, 403);
        abort_unless($reservation->isTransfer(), 404);

        if (! $reservation->isPaymentPending()) {
            return back()->with('error', 'Esta reservación ya no está pendiente de pago.');
        }

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        if ($reservation->transfer_proof_path) {
            Storage::disk('local')->delete($reservation->transfer_proof_path);
        }

        $path = $request->file('proof')->store('transfer-proofs', 'local');
        $reservation->update(['transfer_proof_path' => $path]);

        // Send the customer back wherever they uploaded from — the
        // payment-pending page right after checkout, or "Mis compras"
        // if they came back later to finish the requirement.
        return back()
            ->with('success', 'Comprobante recibido. Te avisaremos por correo en cuanto se valide tu pago.');
    }

    public function success(Request $request, SeatReservation $reservation): View
    {
        // Guest checkouts keep working with this access check: a guest
        // reservation has user_id = null and the request also has no
        // authenticated user, so the comparison `null === null` is true
        // and the guest can see their own (and only their own, if they
        // were redirected straight from store()). Acceptable trade-off
        // for v1 — a guest who guesses / brute-forces a sequential
        // reservation id sees the same data the original buyer saw
        // (no card numbers, no proof-of-payment), so the leak surface
        // is just "is this trip taken?" — not adding signed URLs yet.
        abort_unless($reservation->user_id === $request->user()?->id, 403);

        return view('payment.success', ['reservation' => $reservation->load('landingRoute.busUnit', 'seat')]);
    }

    public function pending(Request $request, SeatReservation $reservation): View
    {
        // See success() above — same intentional guest-access rule.
        abort_unless($reservation->user_id === $request->user()?->id, 403);

        // A multi-seat purchase creates one SeatReservation per seat
        // (linked via notes='group:{root_id}'). The checkout redirects
        // to the ROOT reservation — so we also pull every sibling here
        // and pass them to the blade. Without this, paying for 4 seats
        // would only display seat #1's details and the customer would
        // think only one ticket was issued.
        $reservation->loadMissing(['landingRoute.busUnit', 'seat']);
        $group = $reservation->groupMembers()->load('seat');

        return view('payment.pending', [
            'reservation' => $reservation,
            'group' => $group,
            'paymentMethods' => $reservation->isTransfer() ? PaymentMethod::active()->get() : collect(),
        ]);
    }

    public function error(Request $request, SeatReservation $reservation): View
    {
        // See success() above — same intentional guest-access rule.
        abort_unless($reservation->user_id === $request->user()?->id, 403);

        return view('payment.error', ['reservation' => $reservation->load('landingRoute.busUnit', 'seat')]);
    }

    private function defaultTripTypeFor(LandingRoute $trip): string
    {
        $prices = $trip->activePrices();
        if ($prices->isEmpty()) return TripTicketPrice::TYPE_ONE_WAY;
        $cheapest = $prices->sortBy('price')->keys()->first();
        return $cheapest ?: TripTicketPrice::TYPE_ONE_WAY;
    }
}
