<?php

namespace App\Console\Commands;

use App\Models\LandingRoute;
use Illuminate\Console\Command;

class ActivateRecurringTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trips:activate-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Opens real, dated trips for today AND tomorrow for every recurring route whose pattern covers that day — tomorrow\'s is the one that matters: a trip departing just after midnight needs to already exist by today\'s reminder run (09:00) for SendDayBeforeReminders to find it, not wait until its own day arrives. Today\'s is just a safety net in case a run was ever missed.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $today = LandingRoute::activateRecurringFor(now());
        $tomorrow = LandingRoute::activateRecurringFor(now()->addDay());

        $this->info("Activated {$today->count()} recurring trip(s) for today, {$tomorrow->count()} for tomorrow.");
    }
}
