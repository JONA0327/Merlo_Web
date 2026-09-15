<?php

namespace App\Console\Commands;

use App\Models\SeatReservation;
use Illuminate\Console\Command;

class PruneExpiredTransferReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfer-reservations:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release seats held by bank-transfer reservations whose 3-day validation window lapsed without an admin confirming payment';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Only root rows: group children (notes = 'group:{id}') are
        // released together by releaseGroupAndFreeSeat(), so processing
        // a child again here would double-count / error on a missing row.
        $expired = SeatReservation::query()
            ->where('payment_method', SeatReservation::PAYMENT_METHOD_TRANSFER)
            ->where('payment_status', SeatReservation::PAYMENT_PENDING)
            ->where('transfer_expires_at', '<', now())
            ->whereNull('notes')
            ->get();

        foreach ($expired as $reservation) {
            $reservation->releaseGroupAndFreeSeat();
        }

        $this->info("Released {$expired->count()} expired transfer reservation group(s).");
    }
}
