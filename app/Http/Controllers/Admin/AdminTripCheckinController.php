<?php

namespace App\Http\Controllers\Admin;

use App\Events\SeatAvailabilityUpdated;
use App\Http\Controllers\Controller;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Services\EvolutionWhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminTripCheckinController extends Controller
{
    /**
     * Operator's per-trip check-in dashboard. Replaces the old "scan
     * a ticket code" form with a per-trip view: the page automatically
     * finds the trip that's running today, groups its reservations
     * by check-in state (pending / boarded / etc.), and shows every
     * ticket's full details inline — so the operator taps a single
     * button to register a boarding instead of scanning a QR.
     *
     * If no trip is scheduled for today, the page falls back to the
     * most recent active trip (so the operator can still see and
     * manage check-ins from earlier in the day or week). The recent
     * trips list lets them jump to any trip manually.
     */
    public function index(Request $request): View
    {
        $requestedTripId = $request->query('trip');

        // 1. Pick the trip to show: explicit query param wins; otherwise
        //    today (with a sensible fallback if there's nothing today).
        $trip = null;
        if ($requestedTripId !== null) {
            $trip = LandingRoute::find($requestedTripId);
        }
        if (! $trip) {
            $trip = LandingRoute::query()
                ->whereDate('day', today())
                ->where('is_active', true)
                ->orderBy('departure_time')
                ->first();
        }
        if (! $trip) {
            $trip = LandingRoute::query()
                ->where('is_active', true)
                ->where('day', '<=', today())
                ->orderByDesc('day')
                ->orderByDesc('departure_time')
                ->first();
        }

        // 2. Load every reservation for that trip, split by check-in
        //    state. The grouping mirrors what the operator actually
        //    does at the door: register boarding, then (for round-trips)
        //    mark the return later — so the order is "pending boarding"
        //    → "boarded, return pending" → "fully done".
        $reservations = collect();
        $pendingBoarding = collect();
        $returnPending = collect();
        $fullyDone = collect();

        if ($trip) {
            $reservations = $trip->seatReservations()
                ->with(['seat', 'outboundVerifiedBy', 'returnVerifiedBy'])
                ->orderBy('id')
                ->get();

            $pendingBoarding = $reservations->filter(function (SeatReservation $r) {
                return ! $r->isOutboundVerified() && ! $r->isReturnLeg();
            });
            $returnPending = $reservations->filter(function (SeatReservation $r) {
                return $r->isOutboundVerified()
                    && ($r->needsBothLegs() || $r->isReturnLeg())
                    && ! $r->isReturnVerified();
            });
            $fullyDone = $reservations->filter(function (SeatReservation $r) {
                if ($r->isReturnLeg()) {
                    return $r->isReturnVerified();
                }
                if ($r->needsBothLegs()) {
                    return $r->isOutboundVerified() && $r->isReturnVerified();
                }
                return $r->isOutboundVerified();
            });
        }

        // 3. A short list of recent trips for the switcher, so the
        //    operator can jump between trips on a busy day or back-fill
        //    yesterday's check-ins. Today is highlighted so the "back
        //    to today" intent is obvious.
        $recentTrips = LandingRoute::query()
            ->where('is_active', true)
            ->whereBetween('day', [today()->subDays(7), today()->addDays(2)])
            ->orderByDesc('day')
            ->orderBy('departure_time')
            ->get();

        return view('admin.checkin.index', [
            'trip' => $trip,
            'reservations' => $reservations,
            'pendingBoarding' => $pendingBoarding,
            'returnPending' => $returnPending,
            'fullyDone' => $fullyDone,
            'recentTrips' => $recentTrips,
            'isShowingToday' => $trip && $trip->day && $trip->day->isSameDay(today()),
        ]);
    }

    /**
     * The QR encodes a URL like /admin/checkin/{code} so scanning
     * it on the operator's phone/tablet drops them straight into the
     * ticket detail page without any typing.
     *
     * Kept for backward compatibility with already-printed ticket
     * images — if an old QR gets scanned, we still resolve it here.
     * New check-ins should use the per-trip dashboard instead.
     *
     * If the code doesn't resolve to any reservation, we render the
     * lookup view with a "Código inválido" message instead of 404 —
     * QR codes that get smudged or partially scanned happen often
     * enough that a polite "no match" beats a hard error here.
     */
    public function lookup(Request $request, ?string $code = null): View
    {
        $scanned = $code !== null;
        $code = $code ?? trim((string) $request->input('code', ''));
        $reservation = null;
        $notFound = false;

        if ($code !== '') {
            // Single lookup. We trim + uppercase so the operator can
            // paste "abcd-..." or "ABCD-..." interchangeably and still
            // hit the row.
            $reservation = SeatReservation::with(['landingRoute.busUnit', 'seat', 'outboundVerifiedBy', 'returnVerifiedBy'])
                ->where('ticket_code', strtoupper($code))
                ->first();
            if (! $reservation) {
                $notFound = true;
            }
        }

        // Only loaded when actually eligible — a customer who's already
        // boarded their return, already rescheduled once, or holds a
        // one-way ticket never sees this list, so no need to query it.
        $returnChangeOptions = collect();
        if ($reservation && $reservation->canAdminRescheduleReturn()) {
            $returnChangeOptions = $reservation->returnRescheduleOptions();
        }

        return view('admin.checkin.show', [
            'reservation' => $reservation,
            'scanned' => $scanned,
            'code' => $code,
            'notFound' => $notFound,
            'returnChangeOptions' => $returnChangeOptions,
        ]);
    }

    /**
     * Stamp the outbound leg. For one-way tickets this is the only
     * verification the ticket ever needs.
     */
    public function verifyOutbound(Request $request, SeatReservation $reservation): RedirectResponse
    {
        if ($reservation->isReturnLeg()) {
            return $this->backWithError($reservation, 'Este boleto es solo de regreso — no tiene ida que registrar.');
        }

        if ($reservation->isOutboundVerified()) {
            return $this->backWithError($reservation, 'Esta salida ya estaba registrada.');
        }

        $reservation->update([
            'outbound_verified_at' => now(),
            'outbound_verified_by' => $request->user()?->id,
        ]);

        return $this->backWithSuccess(
            $reservation,
            'Salida registrada para '.$reservation->customer_display_name.'.'
        );
    }

    /**
     * Stamp the return leg. Only meaningful for round-trip tickets
     * — the UI hides the button on one-way rows, but we double-check
     * server-side so a forged POST can't bypass the rule.
     */
    public function verifyReturn(Request $request, SeatReservation $reservation): RedirectResponse
    {
        if ($reservation->isOneWay() && ! $reservation->isReturnLeg()) {
            return $this->backWithError($reservation, 'Este boleto es solo de ida — no tiene vuelta que registrar.');
        }

        if (! $reservation->isReturnLeg() && ! $reservation->isOutboundVerified()) {
            return $this->backWithError($reservation, 'Primero registra la salida antes de marcar el regreso.');
        }

        if ($reservation->isReturnVerified()) {
            return $this->backWithError($reservation, 'Este regreso ya estaba registrado.');
        }

        $reservation->update([
            'return_verified_at' => now(),
            'return_verified_by' => $request->user()?->id,
        ]);

        return $this->backWithSuccess(
            $reservation,
            'Regreso registrado para '.$reservation->customer_display_name.'.'
        );
    }

    /**
     * Admin-driven return reschedule from the check-in detail page: the
     * customer says they won't make their scheduled return, the operator
     * picks the new date right there (from an already-published trip on
     * the reverse route, or just a date if none exists yet), and this
     * immediately creates the replacement one-way return ticket — mirrors
     * ClientDashboardController::confirmReturnChange() (same "new seat,
     * not necessarily the old one, subject to availability, no charge"
     * rules) but skips that flow's request→5-day-window dance since the
     * admin is deciding this in person, right now. Exactly one shot:
     * canAdminRescheduleReturn() blocks doing this again once it's done.
     *
     * SeatReservation::createReturnLegTicket() decides on its own whether
     * the chosen date already has an open trip or needs to be staged
     * against a TripGuide (auto-links once a matching trip is created —
     * see TripGuide::linkTrip()), so this controller doesn't need to care
     * which case it is.
     */
    public function rescheduleReturn(Request $request, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp): RedirectResponse
    {
        if (! $reservation->canAdminRescheduleReturn()) {
            return $this->backToDetail($reservation, 'error', 'Este boleto no puede reprogramar su regreso.');
        }

        $validated = $request->validate([
            'landing_route_id' => ['nullable', 'integer', 'exists:landing_routes,id'],
            'travel_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $route = $reservation->outboundRouteInfo();

        $date = null;
        if (! empty($validated['landing_route_id'])) {
            $picked = LandingRoute::find($validated['landing_route_id']);
            abort_if($picked && ($picked->from !== $route['to'] || $picked->to !== $route['from']), 422, 'El viaje elegido no corresponde al regreso de esta ruta.');
            $date = $picked?->day ? Carbon::parse($picked->day)->startOfDay() : null;
        } elseif (! empty($validated['travel_date'])) {
            $date = Carbon::parse($validated['travel_date'])->startOfDay();
        }

        if (! $date) {
            return $this->backToDetail($reservation, 'error', 'Elige una fecha de regreso.');
        }

        try {
            $newTicket = $reservation->createReturnLegTicket($date);

            // The seat's ORIGINAL return leg (same day, this trip's
            // return) is now unused — release it through the same resale
            // mechanism AdminPaymentController::releaseReturn() uses, so
            // it shows up as available for a same-day "regreso" apartado
            // on that seat instead of sitting empty. Time-boxed by the
            // same "vigencia" setting as any other released return.
            $reservation->update([
                'return_changed_to_reservation_id' => $newTicket->id,
                'return_released_at' => now(),
                'return_released_by' => $request->user()?->id,
                'return_resale_expires_at' => now()->addHours(Setting::current()->returnResaleValidityHours()),
            ]);
        } catch (HttpException $e) {
            return $this->backToDetail($reservation, 'error', $e->getMessage() ?: 'No se pudo reprogramar el regreso.');
        }

        if ($newTicket->landing_route_id === null) {
            // No real trip to show yet — go straight to the guide's own
            // map instead of back to the (still trip-less) ticket detail,
            // so the admin actually sees the seat that just got assigned.
            return redirect()
                ->route('admin.guias.show', [$newTicket->tripGuide, 'fecha' => $date->toDateString()])
                ->with('success', 'Regreso agendado para el '.$date->toSpanishLongDate().'. Aún no existe el viaje — en cuanto lo abras, el asiento '.$newTicket->seat?->label.' se asignará automáticamente y podrás enviar el boleto desde Apartar asientos.');
        }

        SeatAvailabilityUpdated::dispatchSafely(
            $newTicket->landing_route_id,
            [['id' => (int) $newTicket->bus_unit_seat_id, 'status' => 'purchased']]
        );

        // Same "real ticket, real QR" delivery as any other activated
        // boleto — email via the existing group-send path, plus
        // WhatsApp when the customer has a phone on file.
        $newTicket->sendGroupTickets();

        $whatsappSent = false;
        if ($whatsapp->isConfigured() && $newTicket->customer_phone) {
            try {
                $whatsapp->sendTicket($newTicket);
                $whatsappSent = true;
            } catch (\Throwable $e) {
                Log::warning('Return-reschedule WhatsApp send failed for reservation '.$newTicket->id.': '.$e->getMessage());
            }
        }
        if ($whatsappSent) {
            $newTicket->update(['status' => SeatReservation::STATUS_SENT, 'ticket_sent_at' => now()]);
        }

        return $this->backToDetail(
            $reservation,
            'success',
            'Regreso reprogramado para el '.$newTicket->landingRoute->day?->toSpanishLongDate().'. Se envió el nuevo boleto'.($whatsappSent ? ' por correo y WhatsApp.' : ' por correo.')
        );
    }

    private function backToDetail(SeatReservation $reservation, string $type, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.checkin.scan', $reservation->ticket_code)
            ->with($type, $message);
    }

    private function backWithSuccess(SeatReservation $reservation, string $message): RedirectResponse
    {
        // After a successful verify, jump back to the per-trip
        // dashboard (the operator needs to see the next passenger,
        // not the lookup screen). Preserves which trip they were on.
        return redirect()
            ->route('admin.checkin.index', ['trip' => $reservation->landing_route_id])
            ->with('success', $message);
    }

    private function backWithError(SeatReservation $reservation, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.checkin.index', ['trip' => $reservation->landing_route_id])
            ->with('error', $message);
    }
}
