<?php

namespace App\Console\Commands;

use App\Models\SeatReservation;
use Illuminate\Console\Command;

class VoidExpiredReturnChangeRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'return-changes:void-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Void the return leg of tickets whose customer requested a return-date change but let the 5-business-day window lapse without picking one';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Only the return leg is voided — the outbound trip already
        // happened (or is untouched) and isn't affected. Doesn't touch
        // available_seats: that capacity was already spent when the
        // round trip was originally purchased, and an admin can still
        // release it for resale afterwards via the existing mechanism.
        $expired = SeatReservation::query()
            ->whereNotNull('return_change_requested_at')
            ->whereNull('return_changed_to_reservation_id')
            ->whereNull('return_voided_at')
            ->whereNull('return_verified_at')
            ->where('return_change_deadline', '<', now())
            ->get();

        foreach ($expired as $reservation) {
            $reservation->update(['return_voided_at' => now()]);
        }

        $this->info("Voided {$expired->count()} expired return-change request(s).");
    }
}
