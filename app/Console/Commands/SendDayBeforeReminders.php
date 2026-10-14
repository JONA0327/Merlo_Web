<?php

namespace App\Console\Commands;

use App\Models\SeatReservation;
use App\Services\EvolutionWhatsAppService;
use App\Services\TicketImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDayBeforeReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:send-day-before-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One day before travel: send the real ticket to paid passengers and a payment reminder with their seat numbers to anyone still unpaid (standing/"de planta" ones included — same paid/unpaid rule as everyone else) — covers real trips and guía-staged apartados alike, any date, not just today\'s guide';

    /**
     * Execute the console command.
     */
    public function handle(EvolutionWhatsAppService $whatsapp, TicketImageService $ticketImages): void
    {
        if (! $whatsapp->isConfigured()) {
            $this->warn('WhatsApp no configurado — nada que enviar.');

            return;
        }

        $tomorrow = now()->addDay()->toDateString();

        // Real-trip reservations whose trip departs tomorrow, OR
        // guide-staged ones (no real trip yet) whose own travel_date is
        // tomorrow — a guide can span weeks, so this isn't limited to
        // whichever guide an admin happens to be looking at.
        $reservations = SeatReservation::whereNull('reminder_sent_at')
            ->whereIn('status', [SeatReservation::STATUS_PENDING, SeatReservation::STATUS_SENT])
            ->where(function ($query) use ($tomorrow) {
                $query->whereHas('landingRoute', fn ($q) => $q->whereDate('day', $tomorrow))
                    ->orWhere(function ($q) use ($tomorrow) {
                        $q->whereNull('landing_route_id')
                            ->whereNotNull('trip_guide_id')
                            ->whereDate('travel_date', $tomorrow);
                    });
            })
            ->get();

        $rootIds = $reservations->map->groupRootId()->unique();

        $sent = 0;
        foreach ($rootIds as $rootId) {
            $root = SeatReservation::find($rootId);
            if (! $root || $root->reminder_sent_at !== null) {
                continue;
            }

            $group = $root->groupMembers();
            $hasRealTrip = $root->landing_route_id !== null;

            // Seat left over from a plantilla switch and not reassigned
            // yet: no ticket until an admin fixes it (reminder_sent_at
            // stays null, so it goes out on the next run once fixed).
            if ($group->contains(fn (SeatReservation $r) => $r->hasSeatMismatch())) {
                Log::warning('Day-before reminder skipped: seat mismatch pending reassignment.', ['root_id' => $root->id]);

                continue;
            }

            try {
                if ($hasRealTrip) {
                    // Splits by payment itself: ticket to whoever's paid,
                    // the same reservation-notice/reminder text to whoever
                    // isn't — same mechanism Apartar asientos already uses.
                    $whatsapp->deliverGroup($group, $ticketImages);
                } else {
                    // Still guide-staged the day before travel — nobody's
                    // opened the real trip yet, so there's no seat map to
                    // generate a QR against. Just the reminder either way;
                    // logged so an admin notices this guide needs attention.
                    $whatsapp->sendReservationNotice($group);
                    Log::warning('Reservation still guide-staged the day before travel.', ['root_id' => $root->id]);
                }

                SeatReservation::whereIn('id', $group->pluck('id'))->update(['reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Day-before reminder failed for reservation '.$root->id.': '.$e->getMessage());
            }
        }

        $this->info("Procesados {$sent} grupo(s) para mañana ({$tomorrow}).");
    }
}
