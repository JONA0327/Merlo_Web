<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatReservation;
use App\Models\TripTicketPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTripCheckinController extends Controller
{
    /**
     * Operator's home for QR-driven check-in. A simple form that
     * accepts the ticket code (typed or pasted from the scanner)
     * and routes to the lookup view.
     */
    public function index(): View
    {
        return view('admin.checkin.index');
    }

    /**
     * The QR encodes a URL like /admin/checkin/{code} so scanning
     * it on the operator's phone/tablet drops them straight into
     * the ticket detail page without any typing.
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

        return view('admin.checkin.show', [
            'reservation' => $reservation,
            'scanned' => $scanned,
            'code' => $code,
            'notFound' => $notFound,
        ]);
    }

    /**
     * Stamp the outbound leg. For one-way tickets this is the only
     * verification the ticket ever needs.
     */
    public function verifyOutbound(Request $request, SeatReservation $reservation): RedirectResponse
    {
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
        if ($reservation->isOneWay()) {
            return $this->backWithError($reservation, 'Este boleto es solo de ida — no tiene vuelta que registrar.');
        }

        if (! $reservation->isOutboundVerified()) {
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

    private function backWithSuccess(SeatReservation $reservation, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.checkin.lookup', ['code' => $reservation->ticket_code])
            ->with('success', $message);
    }

    private function backWithError(SeatReservation $reservation, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.checkin.lookup', ['code' => $reservation->ticket_code])
            ->with('error', $message);
    }
}
