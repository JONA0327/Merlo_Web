<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CombinedTicketsMail;
use App\Mail\SeatApartadoMail;
use App\Models\BusUnitSeat;
use App\Models\LandingRoute;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use App\Services\EvolutionWhatsAppService;
use App\Services\TicketImageService;
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

        // Only admin-created apartados belong on this screen — we filter
        // by customer_name/email being set so legacy client-purchase
        // reservations (which carry a user_id but no customer_name and
        // default to status='pending' after the migration) don't show up
        // here as fake "pendientes". Those are tracked separately as
        // takenIds below.
        $apartadoScope = fn ($q) => $q
            ->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
            ->where(fn ($q2) => $q2->whereNotNull('customer_name')->orWhereNotNull('customer_email'));

        // A multi-seat apartado links its seats via notes = "group:{root_id}"
        // (same convention the online-purchase flow uses) so the whole
        // batch can be shown as ONE card and sent as ONE combined
        // WhatsApp/email message instead of one per seat. A root is
        // whatever ISN'T that group-link marker — either null, or a real
        // free-text admin note on a single-seat apartado.
        $rootScope = fn ($q) => $q->where(fn ($q3) => $q3->whereNull('notes')->orWhere('notes', 'not like', 'group:%'));

        // Lightweight, unpaginated pass used only to color the seat map
        // and total the pending/enviado badges — the "Apartados" list
        // below is paginated separately so a trip with months of history
        // doesn't load (or render) hundreds of cards at once.
        $allReservations = $apartadoScope($landingRoute->seatReservations())->get(['id', 'bus_unit_seat_id', 'status', 'notes']);
        $reservationsBySeat = $allReservations->groupBy('bus_unit_seat_id');
        $roots = $allReservations->filter(fn (SeatReservation $r) => $r->notes === null || ! str_starts_with((string) $r->notes, 'group:'));
        $pendingCount = $roots->where('status', SeatReservation::STATUS_PENDING)->count();
        $sentCount = $roots->where('status', SeatReservation::STATUS_SENT)->count();

        $reservations = $rootScope($apartadoScope($landingRoute->seatReservations()))
            ->with(['reservedBy'])
            ->orderBy('created_at')
            ->paginate(12);

        // Attach each root's other seats (for display only — "3 asientos:
        // A1, A2, A3") without an extra query per row.
        $groupSeatsByNote = $apartadoScope($landingRoute->seatReservations())
            ->whereIn('notes', $reservations->map(fn (SeatReservation $r) => 'group:'.$r->id))
            ->with('seat')
            ->get()
            ->groupBy('notes');
        $reservations->getCollection()->each(function (SeatReservation $r) use ($groupSeatsByNote) {
            $r->setRelation('groupSeats', $groupSeatsByNote->get('group:'.$r->id, collect()));
        });

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
            'pendingCount' => $pendingCount,
            'sentCount' => $sentCount,
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
            'customer_phone' => ['required', 'string', 'max:20'],
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

        // Multiple seats from the same submission are linked into one
        // apartado group — same "notes = group:{root_id}" convention the
        // online-purchase flow already uses (see SeatReservation::sendGroupTickets()).
        // The root (first seat) carries the admin's free-text note; the
        // rest just carry the group link. This is what lets "Enviar
        // boleto" send everything together as ONE WhatsApp image/email
        // instead of one per seat.
        DB::transaction(function () use ($landingRoute, $data, $request, $tripType, $unitPrice) {
            $root = null;
            foreach ($data['seat_ids'] as $seatId) {
                $new = SeatReservation::create([
                    'landing_route_id' => $landingRoute->id,
                    'bus_unit_seat_id' => $seatId,
                    'user_id' => null,
                    'trip_type' => $tripType,
                    'unit_price' => $unitPrice,
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                    'customer_phone' => $data['customer_phone'],
                    'status' => SeatReservation::STATUS_PENDING,
                    'reserved_by' => $request->user()?->id,
                    'notes' => $root ? 'group:'.$root->id : ($data['notes'] ?? null),
                ]);
                $root ??= $new;
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
     * Mark a pending apartado group as "sent" — this is the trigger the
     * admin uses once they want the boleto(s) to actually reach the
     * customer. A single-seat apartado keeps the original flow (one
     * SeatApartadoMail + one WhatsApp QR image); a multi-seat group
     * instead gets ONE combined image (TicketImageService) with every
     * seat's QR, sent as ONE email + ONE WhatsApp message — not one per
     * seat. Either channel failing never blocks the other, or the
     * status flip — a flaky SMTP server or an unconfigured/offline
     * WhatsApp instance must never make it look like the apartado
     * itself failed.
     */
    public function sendTicket(LandingRoute $landingRoute, SeatReservation $reservation, EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): RedirectResponse
    {
        if (! $reservation->isPending()) {
            return back()->with('error', 'Este apartado ya no está pendiente.');
        }

        $group = $reservation->groupMembers()->load(['landingRoute', 'seat'])->filter->isPending()->values();
        if ($group->isEmpty()) {
            return back()->with('error', 'Este apartado ya no está pendiente.');
        }

        $first = $group->first();
        $emailSent = false;
        $whatsappSent = false;
        $whatsappSkipped = ! $whatsapp->isConfigured();
        $imagePath = null;

        try {
            if ($group->count() === 1) {
                if ($first->customer_email) {
                    try {
                        Mail::to($first->customer_email)->send(new SeatApartadoMail($first));
                        $emailSent = true;
                    } catch (\Throwable $e) {
                        Log::warning('Apartado mail failed for reservation '.$first->id.': '.$e->getMessage());
                    }
                }
                if (! $whatsappSkipped) {
                    try {
                        $whatsapp->sendTicket($first);
                        $whatsappSent = true;
                    } catch (\Throwable $e) {
                        Log::warning('Apartado WhatsApp send failed for reservation '.$first->id.': '.$e->getMessage());
                    }
                }
            } else {
                $imagePath = $ticketImages->buildCombinedImage($group);

                if ($first->customer_email) {
                    try {
                        Mail::to($first->customer_email)->send(new CombinedTicketsMail($group, $imagePath));
                        $emailSent = true;
                    } catch (\Throwable $e) {
                        Log::warning('Combined apartado mail failed for group '.$first->id.': '.$e->getMessage());
                    }
                }
                if (! $whatsappSkipped) {
                    try {
                        $whatsapp->sendImageFile(
                            $first->customer_phone,
                            $imagePath,
                            $this->buildGroupCaption($group),
                            'boletos-merlo-'.$first->id.'.jpg'
                        );
                        $whatsappSent = true;
                    } catch (\Throwable $e) {
                        Log::warning('Combined apartado WhatsApp send failed for group '.$first->id.': '.$e->getMessage());
                    }
                }
            }
        } finally {
            if ($imagePath && file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        SeatReservation::whereIn('id', $group->pluck('id'))->update([
            'status' => SeatReservation::STATUS_SENT,
            'ticket_sent_at' => now(),
        ]);

        $plural = $group->count() > 1;
        $channels = array_filter([
            $emailSent ? 'correo' : null,
            $whatsappSent ? 'WhatsApp' : null,
        ]);

        if (! empty($channels)) {
            return back()->with('success', ($plural ? 'Boletos enviados' : 'Boleto enviado').' por '.implode(' y ', $channels).'.');
        }

        $reason = $whatsappSkipped
            ? 'El correo no se pudo entregar y WhatsApp no está configurado — revisa el log.'
            : 'Ni el correo ni WhatsApp se pudieron entregar — revisa el log.';

        return back()->with('success', 'Apartado marcado como enviado. ('.$reason.')');
    }

    /**
     * Cancel / delete a whole apartado group (every seat the admin
     * selected together for this customer). Frees the seats back into
     * the available pool so another customer (or another apartado) can
     * take them.
     */
    public function destroy(LandingRoute $landingRoute, SeatReservation $reservation): RedirectResponse
    {
        $trip = $reservation->landingRoute;
        $group = $reservation->groupMembers();

        SeatReservation::whereIn('id', $group->pluck('id'))->delete();

        return redirect()
            ->route('admin.asientos.show', $trip)
            ->with('success', $group->count() > 1
                ? 'Apartado cancelado ('.$group->count().' asientos). Los asientos vuelven a estar disponibles.'
                : 'Apartado cancelado. El asiento vuelve a estar disponible.');
    }

    private function buildGroupCaption(\Illuminate\Support\Collection $reservations): string
    {
        $first = $reservations->first();
        $trip = $first->landingRoute;
        $seats = $reservations->map(fn (SeatReservation $r) => $r->seat?->label ?? '—')->implode(', ');

        return "*MERLO Transportes* 🚌\n\n"
            ."Hola {$first->customer_display_name}, aquí tienen tus {$reservations->count()} boletos:\n\n"
            ."*{$trip->from} → {$trip->to}*\n"
            ."💺 Asientos: {$seats}\n\n"
            .'Todos tus códigos QR están en esta imagen — muéstrala completa al abordar.';
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
