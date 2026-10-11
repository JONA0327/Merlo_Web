<?php

namespace App\Console\Commands;

use App\Models\LandingRoute;
use App\Models\TripGuide;
use Illuminate\Console\Command;

class ProvisionRecurringGuides extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guides:provision-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Keeps every recurring trip\'s guide rolling ~4 weeks ahead, even if nobody opens /admin/guias for a while';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $trips = LandingRoute::where('is_active', true)->whereNotNull('recurring_weekdays')->get();

        $created = 0;
        foreach ($trips as $trip) {
            $guide = TripGuide::ensureForRecurringTrip($trip);
            if ($guide?->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Provisioned guides for {$trips->count()} recurring trip(s), {$created} newly created.");
    }
}
