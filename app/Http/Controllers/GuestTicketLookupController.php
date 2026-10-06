<?php

namespace App\Http\Controllers;

use App\Models\SeatReservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guest ticket lookup — the "I bought without an account, where are
 * my tickets?" flow.
 *
 * The checkout is now open to guests (see SeatPickerController::store
 * — `user_id` is nullable on the seat_reservations table), so a
 * significant chunk of customers will never log in. They still need
 * a way to come back later and see the QR / pending-transfer
 * details / etc. — so we let them look up their reservations by
 * (name + phone) match. Those two fields are unique-enough in
 * combination that we don't bother with a verification code (the
 * info leaked when a wrong pair is tried is "this person has no
 * reservation under that name+phone", which is harmless), and we
 * also don't reveal the full customer list since the search only
 * runs against the specific pair the user typed.
 */
class GuestTicketLookupController extends Controller
{
    /**
     * The form. Renders the same GET endpoint on failure so the user
     * can correct their input without a redirect loop.
     */
    public function index(Request $request): View
    {
        return view('guest.lookup', [
            'reservations' => null,
            'name' => null,
            'phone' => null,
        ]);
    }

    public function lookup(Request $request): View
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:7', 'max:30'],
        ], [], [
            'customer_name' => 'nombre',
            'customer_phone' => 'teléfono',
        ]);

        // Normalize: strip non-digits from the phone so a user typing
        // "444 123 4567" / "444-123-4567" / "4441234567" all hit the
        // same row. Same for the name — case + extra spaces shouldn't
        // matter when the customer is typing their own name.
        $name = trim($validated['customer_name']);
        $phoneDigits = preg_replace('/\D/', '', $validated['customer_phone']);
        $storedPhoneDigits = '%' . preg_replace('/\D/', '', $phoneDigits) . '%';

        // The reservations we care about are guest checkouts (no user_id)
        // — but we don't filter on user_id IS NULL so the form also works
        // for a customer who buys one trip as a guest, then later creates
        // an account and buys another; matching by name+phone finds
        // both. "Where phone contains digits" is the user-friendly match;
        // a stricter implementation could store a normalized phone column
        // if the format drift gets worse.
        $reservations = SeatReservation::query()
            ->with(['landingRoute', 'seat'])
            ->where(function ($q) use ($name) {
                $q->whereRaw('LOWER(TRIM(customer_name)) = ?', [mb_strtolower($name)]);
            })
            ->where('customer_phone', 'like', $storedPhoneDigits)
            ->where('created_at', '>=', now()->subMonths(6))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Fallback: if no rows matched (e.g. the customer typed their
        // phone with the country prefix and we don't store it), try a
        // suffix match — same idea, but on the last N digits.
        if ($reservations->isEmpty() && strlen($phoneDigits) >= 8) {
            $reservations = SeatReservation::query()
                ->with(['landingRoute', 'seat'])
                ->whereRaw('LOWER(TRIM(customer_name)) = ?', [mb_strtolower($name)])
                ->where('customer_phone', 'like', '%' . substr($phoneDigits, -10))
                ->where('created_at', '>=', now()->subMonths(6))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return view('guest.lookup', [
            'reservations' => $reservations,
            'name' => $name,
            'phone' => $validated['customer_phone'],
        ]);
    }
}