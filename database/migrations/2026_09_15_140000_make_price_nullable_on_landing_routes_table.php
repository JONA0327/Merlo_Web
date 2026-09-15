<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `price` is a leftover column from before pricing moved to the
     * separate trip_ticket_prices table (see TripTicketPrice /
     * LandingRoute::priceFor()) — nothing in the app reads or writes it
     * anymore, but it was left NOT NULL with no default, so every new
     * landing_routes insert (e.g. creating a trip in admin) failed with
     * a "NOT NULL constraint failed: landing_routes.price" error.
     */
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->string('price')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->string('price')->nullable(false)->change();
        });
    }
};
