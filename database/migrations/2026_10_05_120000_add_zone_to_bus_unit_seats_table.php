<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text "zone" tag per seat (e.g. "Mancuerna 1"), assigned from
     * the seat-map editor. Used as a bulk-selection shortcut in Apartar
     * asientos for the "especial"/"regreso" categories — clicking a
     * zone selects every seat tagged with it at once, instead of
     * clicking each seat individually. Nullable: most seats won't
     * belong to any zone.
     */
    public function up(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->string('zone', 40)->nullable()->after('allowed_trip_type');
            $table->index('zone');
        });
    }

    public function down(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->dropIndex(['zone']);
            $table->dropColumn('zone');
        });
    }
};
