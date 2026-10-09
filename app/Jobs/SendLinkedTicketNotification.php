<?php

namespace App\Jobs;

use App\Models\SeatReservation;
use App\Services\EvolutionWhatsAppService;
use App\Services\TicketImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends the "your trip is open now" notification for a group of
 * reservations that just got attached to a real trip — either via
 * TripGuide::linkTrip() (a guía's pending apartados) or a "de planta"
 * standing assignment that auto-booked when the trip was created.
 *
 * Queued, not sent inline: opening a trip can resolve several pending
 * guía groups and standing assignments at once, and firing all of those
 * WhatsApp sends synchronously in the same request both slows down
 * creating the trip and risks hammering the WhatsApp API back to back.
 *
 * Paid groups get the real ticket (QR image + email); unpaid groups get
 * the same plain-text "reservado, paga para activarlo" notice any other
 * cash-pending apartado gets — never a QR before payment is confirmed.
 */
class SendLinkedTicketNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $rootReservationId)
    {
    }

    public function handle(EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): void
    {
        $root = SeatReservation::find($this->rootReservationId);
        if (! $root || ! $root->landing_route_id) {
            return;
        }

        $group = $root->groupMembers()->load(['landingRoute', 'seat']);

        // Never send a ticket or reservation notice for a seat that
        // doesn't actually exist on this trip's bus — TripGuide::
        // linkTrip() still links it (so it shows up in Apartar asientos)
        // but skips dispatching this job for the whole group; this is
        // belt-and-suspenders in case something else ever dispatches it
        // directly. Reassigning the seat re-dispatches this job.
        if ($group->contains(fn (SeatReservation $r) => $r->hasSeatMismatch())) {
            Log::warning('Skipped linked-ticket notification for reservation group '.$root->id.': seat mismatch.');

            return;
        }

        if ($root->isPaymentCompleted()) {
            $root->sendGroupTickets();

            $sent = $whatsapp->sendGroupTicket($group, $ticketImages);
            if ($sent) {
                SeatReservation::whereIn('id', $group->pluck('id'))->update([
                    'status' => SeatReservation::STATUS_SENT,
                    'ticket_sent_at' => now(),
                ]);
            }

            return;
        }

        if (! $whatsapp->isConfigured()) {
            return;
        }

        try {
            $whatsapp->sendReservationNotice($group);
        } catch (\Throwable $e) {
            Log::warning('Linked-guide reservation notice failed for reservation '.$root->id.': '.$e->getMessage());
        }
    }
}
