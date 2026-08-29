<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SeatApartadoMail;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSeatReservationController extends Controller
{
    /**
     * List every trip the admin can apartar seats for, with a quick
     * "pending / sent" counter so they can spot trips that still have
     * unpaid reservations to chase down.
     */
    public function index(): View
    {
        $routes = LandingRoute::query()
            ->withCount([
                // Counter rows are filtered to admin-created apartados only
                // (have customer_name or customer_email) so legacy
                // client-purchase reservations don't pollute the dashboard.
                'seatReservations as pending_reservations_count' => fn ($q) => $q
                    ->where('status', SeatReservation::STATUS_PENDING)
                    ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email')),
                'seatReservations as sent_reservations_count' => fn ($q) => $q
                    ->where('status', SeatReservation::STATUS_SENT)
                    ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email')),
            ])
            ->where('is_active', true)
            ->orderBy('day')
            ->orderBy('departure_time')
            ->get();

        return view('admin.asientos.index', [
            'routes' => $routes,
        ]);
    }

    /**
     * Seat picker for the admin. Same Konva canvas the customer sees on
     * /viajes/{trip}/asientos, but with three differences:
     *   1. The legend marks "pending" (orange) and "sent" (blue)
     *      alongside the normal available / sold colors.
     *   2. There's a form on the side to type the client's name/email
     *      and turn a multi-seat selection into a pending apartado.
     *   3. There's a "Apartados pendientes" panel below that lists
     *      every pending reservation with its "Enviar boleto" button.
     */
    public function show(LandingRoute $landingRoute): View
    {
        abort_unless($landingRoute->hasSeatMap(), 404, 'Este viaje no tiene un mapa de asientos configurado.');

        $landingRoute->load('busUnit.seats', 'prices');

        // Group reservations per seat for quick lookup when rendering the
        // seat map: each seat gets a list of reservations (pending/sent)
        // so the picker can color the right pixel.
        //
        // Only admin-created apartados belong on this screen — we filter
        // by customer_name/email being set so legacy client-purchase
        // reservations (which carry a user_id but no customer_name and
        // default to status='pending' after the migration) don't show up
        // here as fake "pendientes". Those are tracked separately as
        // takenIds below.
        $reservations = $landingRoute->seatReservations()
            ->with(['reservedBy'])
            ->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
            ->where(function ($q) {
                $q->whereNotNull('customer_name')->orWhereNotNull('customer_email');
            })
            ->orderBy('created_at')
            ->get();

        $reservationsBySeat = $reservations->groupBy('bus_unit_seat_id');

        // Real client purchases (no customer_name/email, but a user_id):
        // these are seats that are paid-for and shouldn't be re-apartable.
        $takenIds = $landingRoute->seatReservations()
            ->whereNull('customer_name')
            ->whereNull('customer_email')
            ->whereNotNull('user_id')
            ->pluck('bus_unit_seat_id');

        return view('admin.asientos.show', [
            'trip' => $landingRoute,
            'reservations' => $reservations,
            'reservationsBySeat' => $reservationsBySeat,
            'takenIds' => $takenIds,
        ]);
    }

    /**
     * Persist a new apartado. Each selected seat becomes its own
     * SeatReservation row (one per seat, not one per client) so the
     * existing client-purchase flow keeps working without changes:
     * apartados just add pending rows on top, and the same "is this
     * seat taken?" query already filters them out.
     */
    public function store(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:180'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $landingRoute->bus_unit_id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tripType = $data['trip_type'];
        $unitPrice = (float) ($landingRoute->priceFor($tripType)?->price ?? 0);

        // Reject seats that are already taken by either a confirmed
        // client purchase (user_id set) or another active apartado
        // (pending/sent). A double-booking would surface in the picker
        // as soon as both admins refreshed, so we block it here while
        // the form was still open.
        $alreadyTaken = $landingRoute->seatReservations()
            ->whereIn('bus_unit_seat_id', $data['seat_ids'])
            ->where(function ($q) {
                $q->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
                    ->orWhereNotNull('user_id');
            })
            ->pluck('bus_unit_seat_id')
            ->all();

        if (! empty($alreadyTaken)) {
            $labels = BusUnitSeat::whereIn('id', $alreadyTaken)->pluck('label')->all();

            return back()
                ->withInput()
                ->with('error', 'Los siguientes asientos ya están apartados: '.implode(', ', $labels));
        }

        // Server-side check for the per-seat trip-type restriction —
        // mirrors what the JS already does on the picker so a hand-crafted
        // POST can't sneak through.
        $mismatched = $landingRoute->busUnit->seats()
            ->whereIn('id', $data['seat_ids'])
            ->get()
            ->filter(fn ($seat) => ! $seat->allowsTripType($tripType))
            ->pluck('label')
            ->all();

        if (! empty($mismatched)) {
            return back()
                ->withInput()
                ->with('error', 'Estos asientos no están disponibles para el tipo de viaje seleccionado: '.implode(', ', $mismatched));
        }

        DB::transaction(function () use ($landingRoute, $data, $request, $tripType, $unitPrice) {
            foreach ($data['seat_ids'] as $seatId) {
                SeatReservation::create([
                    'landing_route_id' => $landingRoute->id,
                    'bus_unit_seat_id' => $seatId,
                    'user_id' => null,
                    'trip_type' => $tripType,
                    'unit_price' => $unitPrice,
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                    'status' => SeatReservation::STATUS_PENDING,
                    'reserved_by' => $request->user()?->id,
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.asientos.show', $landingRoute)
            ->with('success', sprintf(
                'Apartado creado para %s (%d asiento%s, %s).',
                $data['customer_name'],
                count($data['seat_ids']),
                count($data['seat_ids']) === 1 ? '' : 's',
                TripTicketPrice::tripTypes()[$tripType] ?? $tripType
            ));
    }

    /**
     * Mark a pending apartado as "sent" — this is the trigger the admin
     * uses once they want the boleto to actually reach the customer.
     * Sends the digital ticket (SeatApartadoMail) with the QR code
     * embedded, then flips the apartado to 'sent' so the seat counts
     * as occupied.
     */
    public function sendTicket(LandingRoute $landingRoute, SeatReservation $reservation): RedirectResponse
    {
        if (! $reservation->isPending()) {
            return back()->with('error', 'Este apartado ya no está pendiente.');
        }

        $sent = false;
        if ($reservation->customer_email) {
            try {
                Mail::to($reservation->customer_email)->send(new SeatApartadoMail($reservation));
                $sent = true;
            } catch (\Throwable $e) {
                // Don't fail the action just because the SMTP server is
                // grumpy — the admin still wants the apartado flipped to
                // "sent" so the seat goes back to "available for new
                // customer" tracking. Log so we can chase the mail issue
                // separately.
                Log::warning('Apartado mail failed for reservation '.$reservation->id.': '.$e->getMessage());
            }
        }

        $reservation->update([
            'status' => SeatReservation::STATUS_SENT,
            'ticket_sent_at' => now(),
        ]);

        return back()->with('success', $sent
            ? 'Boleto enviado al correo del cliente.'
            : 'Apartado marcado como enviado. (El correo no se pudo entregar — revisa el log.)');
    }

    /**
     * Cancel / delete a single reservation in an apartado. Frees the seat
     * back into the available pool so another customer (or another
     * apartado) can take it.
     */
    public function destroy(LandingRoute $landingRoute, SeatReservation $reservation): RedirectResponse
    {
        $trip = $reservation->landingRoute;
        $reservation->delete();

        return redirect()
            ->route('admin.asientos.show', $trip)
            ->with('success', 'Apartado cancelado. El asiento vuelve a estar disponible.');
    }

    /**
     * "Disponibilidad" — the per-seat "this is bookable for X trip type
     * only" editor. Distinct from the per-unit editor (bus_units
     * editor) so the admin can set trip-specific rules without having
     * to dig into the unit's layout. Renders the same Konva seat map
     * with a "paint" mode UI: pick a trip type, then click seats to
     * mark them.
     */
    public function availability(LandingRoute $landingRoute): View
    {
        abort_unless($landingRoute->hasSeatMap(), 404, 'Este viaje no tiene un mapa de asientos configurado.');

        $landingRoute->load('busUnit.seats');

        // Summary of how the seats are currently distributed across the
        // three modes, for the legend/counter at the top of the page.
        $stats = $landingRoute->busUnit->seats
            ->where('kind', 'seat')
            ->groupBy(fn ($seat) => $seat->allowed_trip_type ?? 'both')
            ->map->count();

        return view('admin.asientos.availability', [
            'trip' => $landingRoute,
            'stats' => [
                'both' => $stats['both'] ?? 0,
                'one_way' => $stats['one_way'] ?? 0,
                'round_trip' => $stats['round_trip'] ?? 0,
            ],
        ]);
    }

    /**
     * Bulk update of allowed_trip_type for the trip's seats. The client
     * sends only the seats it changed (with their new value), so the
     * payload stays small even when the trip has dozens of seats.
     */
    public function updateAvailability(Request $request, LandingRoute $landingRoute): RedirectResponse
    {
        abort_unless($landingRoute->hasSeatMap(), 404);

        $data = $request->validate([
            'changes' => ['required', 'array'],
            'changes.*.id' => [
                'required', 'integer',
                Rule::exists('bus_unit_seats', 'id')->where('bus_unit_id', $landingRoute->bus_unit_id),
            ],
            'changes.*.allowed_trip_type' => ['required', 'string', 'in:both,one_way,round_trip'],
        ]);

        $busUnitId = $landingRoute->bus_unit_id;
        $count = 0;

        DB::transaction(function () use ($data, $busUnitId, &$count) {
            foreach ($data['changes'] as $change) {
                $affected = DB::table('bus_unit_seats')
                    ->where('id', $change['id'])
                    ->where('bus_unit_id', $busUnitId)
                    ->update(['allowed_trip_type' => $change['allowed_trip_type']]);
                $count += $affected;
            }
        });

        return redirect()
            ->route('admin.asientos.availability', $landingRoute)
            ->with('success', "Disponibilidad actualizada ({$count} asiento".($count === 1 ? '' : 's').').');
    }
}
