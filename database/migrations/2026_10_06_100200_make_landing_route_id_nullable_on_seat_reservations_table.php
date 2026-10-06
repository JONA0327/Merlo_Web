<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "guía" reservation holds a seat for a future date before the real
     * trip (LandingRoute) exists at all — landing_route_id stays null
     * until TripGuide::linkTrip() finds a matching trip and fills it in.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreignId('landing_route_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreignId('landing_route_id')->nullable(false)->change();
        });
    }
};
