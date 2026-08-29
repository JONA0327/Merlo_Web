<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-seat "which kind of ticket is this seat allowed for".
     *   - both       : available for one-way and round-trip (default)
     *   - one_way    : only when the customer is buying a single leg
     *   - round_trip : only when the customer buys a round-trip ticket
     *
     * The seat-picker and admin-asientos views filter the visible
     * selection using this column, so a seat marked 'one_way' simply
     * shows up greyed-out when the trip type toggle is set to
     * "Redondo".
     */
    public function up(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->string('allowed_trip_type', 20)->default('both')->after('kind');
            $table->index('allowed_trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->dropIndex(['allowed_trip_type']);
            $table->dropColumn('allowed_trip_type');
        });
    }
};
