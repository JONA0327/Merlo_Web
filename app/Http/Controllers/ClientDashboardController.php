<?php

namespace App\Http\Controllers;

use App\Events\SeatAvailabilityUpdated;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientDashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (auth()->user()->role === User::ROLE_PAQUETERIA) {
            return redirect()->route('admin.paqueteria');
        }

        return view('client.dashboard');
    }

    public function compras(): View
    {
        // Purchase groups: one card per checkout (the primary row plus
        // any linked multi-seat rows, notes = "group:{id}") rather than
        // one per seat — matches how SeatReservation itself groups a
        // purchase everywhere else (sendGroupTickets(), markGroupPaid()).
        $reservations = auth()->user()->seatReservations()
            ->with(['landingRoute', 'seat'])
            ->latest()
            ->get();

        $roots = $reservations->reject(fn (SeatReservation $r) => str_starts_with((string) $r->notes, 'group:'));

        $purchases = $roots->map(function (SeatReservation $root) use ($reservations) {
            $children = $reservations->filter(fn (SeatReservation $r) => $r->notes === 'group:'.$root->id)->values();

            return (object) [
                'root' => $root,
                'seats' => collect([$root])->merge($children),
            ];
        })->values();

        return view('client.compras', [
            'purchases' => $purchases,
        ]);
    }

    public function paquetes(): View
    {
        return view('client.paquetes');
    }

    public function boletos(): View
    {
        // Only confirmed tickets — a still-pending purchase (e.g. a
        // transfer awaiting validation) isn't a usable boarding pass
        // yet, so it belongs in "Mis compras" instead, not here.
        $reservations = auth()->user()->seatReservations()
            ->where('payment_status', SeatReservation::PAYMENT_COMPLETED)
            ->with(['landingRoute', 'seat'])
            ->latest()
            ->get()
            ->groupBy('landing_route_id');

        return view('client.boletos', [
            'reservations' => $reservations,
        ]);
    }

    public function verBoleto(SeatReservation $reservation): View
    {
        abort_unless($reservation->user_id === auth()->id(), 403);
        abort_unless($reservation->isPaymentCompleted(), 404);

        // unit_price/total store the WHOLE purchase's total on every row
        // in the group (root and children alike) — divide by the seat
        // count so a single seat's ticket shows its own price, not the
        // full multi-seat total.
        $rootId = str_starts_with((string) $reservation->notes, 'group:')
            ? (int) substr($reservation->notes, strlen('group:'))
            : $reservation->id;
        $seatCount = max(1, SeatReservation::where('id', $rootId)->orWhere('notes', 'group:'.$rootId)->count());

        return view('client.ticket', [
            'reservation' => $reservation->load(['landingRoute.busUnit', 'seat']),
            'seatCount' => $seatCount,
        ]);
    }

    /**
     * Customer marks "I won't make my scheduled return." Starts a
     * fixed 5-business-day window (see SeatReservation::businessDaysFromNow())
     * during which they must pick a replacement date themselves — if
     * they don't, the return leg is voided by the
     * return-changes:void-expired scheduled command. No money moves
     * either way; this is a pure reassignment.
     */
    public function requestReturnChange(SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->user_id === auth()->id(), 403);

        if (! $reservation->canRequestReturnChange()) {
            return back()->with('error', 'Este boleto no puede solicitar un cambio de fecha de regreso.');
        }

        $reservation->update([
            'return_change_requested_at' => now(),
            'return_change_deadline' => SeatReservation::businessDaysFromNow(5),
        ]);

        return back()->with('success', 'Solicitud registrada. Tienes hasta el '.$reservation->return_change_deadline->format('d/m/Y H:i').' para elegir tu nueva fecha de regreso; si no la eliges, tu regreso quedará anulado.');
    }

    public function chooseReturnDate(SeatReservation $reservation): View|RedirectResponse
    {
        abort_unless($reservation->user_id === auth()->id(), 403);

        if (! $reservation->isReturnChangePending()) {
            return redirect()->route('cliente.boletos.ver', $reservation)
                ->with('error', 'Este boleto no tiene una solicitud de cambio de regreso vigente.');
        }

        $reservation->load('landingRoute');

        // A return travels the OPPOSITE direction of the original trip
        // (destination back to origin) — e.g. an original "San Luis
        // Potosí → Ciudad de México" ticket needs replacement options
        // among published "Ciudad de México → San Luis Potosí" trips,
        // not more of the same direction. Only already-published trips,
        // with real seat-map capacity, departing in the future, and
        // with available seats — never a free-text date the admin has
        // to fulfill.
        $options = LandingRoute::query()
            ->where('from', $reservation->landingRoute->to)
            ->where('to', $reservation->landingRoute->from)
            ->where('is_active', true)
            ->whereNotNull('bus_unit_id')
            ->where('available_seats', '>', 0)
            ->where('day', '>=', now()->toDateString())
            ->orderBy('day')
            ->get();

        return view('client.return-change', [
            'reservation' => $reservation,
            'options' => $options,
        ]);
    }

    /**
     * Reassign the return leg to a different, already-published trip.
     * Creates exactly ONE new ticket (leg = return, linked back via
     * source_reservation_id) for a seat on the destination trip that is
     * NOT the same seat as the original — it's whatever's first
     * available there, made clear to the customer before they confirm.
     * No charge, no refund: unit_price/subtotal/tax/total are all 0.
     */
    public function confirmReturnChange(Request $request, SeatReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->user_id === auth()->id(), 403);

        if (! $reservation->isReturnChangePending()) {
            return redirect()->route('cliente.boletos.ver', $reservation)
                ->with('error', 'Este boleto no tiene una solicitud de cambio de regreso vigente.');
        }

        $validated = $request->validate([
            'landing_route_id' => ['required', 'integer', 'exists:landing_routes,id'],
        ]);

        $reservation->load('landingRoute');

        try {
            $newTicket = DB::transaction(function () use ($reservation, $validated) {
                $target = LandingRoute::query()->lockForUpdate()->findOrFail($validated['landing_route_id']);

                // Must travel the reverse direction of the original trip.
                abort_if($target->from !== $reservation->landingRoute->to || $target->to !== $reservation->landingRoute->from, 422, 'El viaje elegido no corresponde al regreso de esta ruta.');
                abort_if(! $target->hasSeatMap(), 422, 'El viaje elegido no tiene asientos disponibles.');
                abort_if($target->available_seats < 1, 409, 'Ya no hay asientos disponibles en la fecha elegida.');

                $takenIds = $target->seatReservations()->pluck('bus_unit_seat_id');
                $seat = $target->busUnit->seats()
                    ->bookable()
                    ->whereNotIn('id', $takenIds)
                    ->get()
                    ->first(fn (BusUnitSeat $s) => $s->allowsTripType(TripTicketPrice::TYPE_ONE_WAY));

                abort_if(! $seat, 409, 'Ya no hay asientos disponibles en la fecha elegida.');

                // Not leg=return: this is a genuinely separate, fully
                // self-contained one-way ticket on the reverse-direction
                // trip (own departure date, own seat map) — every other
                // passenger on that bus boards it as a normal one-way
                // ticket, so this one checks in and displays its date
                // (trip->day) the same way. source_reservation_id is
                // what links it back to the original for traceability.
                $newTicket = SeatReservation::create([
                    'landing_route_id' => $target->id,
                    'bus_unit_seat_id' => $seat->id,
                    'user_id' => $reservation->user_id,
                    'trip_type' => TripTicketPrice::TYPE_ONE_WAY,
                    'source_reservation_id' => $reservation->id,
                    'unit_price' => 0,
                    'payment_method' => $reservation->payment_method,
                    'payment_status' => SeatReservation::PAYMENT_COMPLETED,
                    'paid_at' => now(),
                    'subtotal' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'currency' => 'MXN',
                    'customer_name' => $reservation->customer_name,
                    'customer_email' => $reservation->customer_email,
                    'customer_phone' => $reservation->customer_phone,
                    'ip_address' => request()->ip(),
                    'notes' => 'Cambio de regreso del boleto #'.$reservation->id,
                ]);

                $target->decrement('available_seats');
                $reservation->update(['return_changed_to_reservation_id' => $newTicket->id]);

                return $newTicket;
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', $e->getMessage() ?: 'No se pudo confirmar el cambio de regreso.');
        }

        SeatAvailabilityUpdated::dispatchSafely(
            $newTicket->landing_route_id,
            [['id' => (int) $newTicket->bus_unit_seat_id, 'status' => 'purchased']]
        );

        $newTicket->sendGroupTickets();

        return redirect()->route('cliente.boletos.ver', $newTicket)
            ->with('success', 'Tu nueva fecha de regreso quedó confirmada. Recuerda: es un asiento distinto al original, asignado según disponibilidad.');
    }
}
