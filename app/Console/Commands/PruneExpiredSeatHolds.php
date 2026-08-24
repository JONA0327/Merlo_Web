<?php

namespace App\Console\Commands;

use App\Models\SeatHold;
use Illuminate\Console\Command;

class PruneExpiredSeatHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seat-holds:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired seat holds (housekeeping only — expired holds are already ignored everywhere they matter)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $deleted = SeatHold::where('expires_at', '<', now())->delete();

        $this->info("Pruned {$deleted} expired seat hold(s).");
    }
}
