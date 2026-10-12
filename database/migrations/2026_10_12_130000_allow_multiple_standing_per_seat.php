<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One seat can be "de planta" for different people on different
    // weekdays, so it can no longer be unique per seat.
    public function up(): void
    {
        Schema::table('standing_reservations', function (Blueprint $table) {
            $table->dropUnique('standing_unique');
            $table->index(['from', 'to', 'bus_unit_id', 'bus_unit_seat_id'], 'standing_seat_index');
        });
    }

    public function down(): void
    {
        Schema::table('standing_reservations', function (Blueprint $table) {
            $table->dropIndex('standing_seat_index');
            $table->unique(['from', 'to', 'bus_unit_id', 'bus_unit_seat_id'], 'standing_unique');
        });
    }
};
