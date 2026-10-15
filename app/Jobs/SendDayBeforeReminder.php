<?php

namespace App\Jobs;

use App\Models\SeatReservation;
use App\Services\EvolutionWhatsAppService;
use App\Services\TicketImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Aviso de "un día antes" de un grupo de apartados, mandado por cola
 * (escalonado por SendDayBeforeReminders) para no enviar todo de golpe.
 * El comando marca reminder_sent_at al despachar; si el envío falla se
 * libera para que la siguiente corrida lo reintente.
 */
class SendDayBeforeReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $rootReservationId)
    {
        $this->onQueue('reminders');
    }

    public function handle(EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): void
    {
        $root = SeatReservation::find($this->rootReservationId);
        if (! $root) {
            return;
        }

        $group = $root->groupMembers()->load(['landingRoute', 'seat']);

        try {
            $ok = $root->landing_route_id !== null
                ? $whatsapp->deliverGroup($group, $ticketImages)
                : tap(true, fn () => $whatsapp->sendReservationNotice($group));
        } catch (\Throwable $e) {
            Log::warning('Day-before reminder failed for reservation '.$root->id.': '.$e->getMessage());
            $ok = false;
        }

        if (! $ok) {
            SeatReservation::whereIn('id', $group->pluck('id'))->update(['reminder_sent_at' => null]);
        }
    }
}
