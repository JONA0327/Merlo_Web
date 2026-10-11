<?php

namespace App\Console\Commands;

use App\Models\LandingRoute;
use App\Models\StandingReservation;
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
    protected $description = 'Keeps every recurring trip\'s guide rolling ~4 weeks ahead and every "de planta" assignment staged as a real apartado for each upcoming matching day, even if nobody opens /admin/guias for a while';

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

        $staged = 0;
        foreach (TripGuide::all() as $guide) {
            $staged += StandingReservation::provisionGuideReservations($guide);
        }

        $this->info("Provisioned guides for {$trips->count()} recurring trip(s), {$created} newly created. {$staged} standing apartado(s) staged.");
    }
}
