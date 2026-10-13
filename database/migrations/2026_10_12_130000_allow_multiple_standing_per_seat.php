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
        // The unique index's actual name can drift from the literal
        // 'standing_unique' the original migration asked for (SQLite in
        // particular has renamed it in some environments) — find
        // whichever unique index actually covers these columns instead
        // of assuming the name, so this runs the same everywhere.
        $target = ['from', 'to', 'bus_unit_id', 'bus_unit_seat_id'];
        foreach (Schema::getIndexes('standing_reservations') as $index) {
            if ($index['unique'] && $index['columns'] === $target) {
                Schema::table('standing_reservations', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }

        Schema::table('standing_reservations', function (Blueprint $table) {
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
