<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreignId('trip_guide_id')->nullable()->after('landing_route_id')->constrained()->nullOnDelete();
            // The specific date (within the guide's date_from/date_to range)
            // this seat is being held for, while there's no real trip yet to
            // hang a day off of. Null once a real LandingRoute exists —
            // landing_route_id + its own `day` take over from here.
            $table->date('travel_date')->nullable()->after('trip_guide_id');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_guide_id');
            $table->dropColumn('travel_date');
        });
    }
};
