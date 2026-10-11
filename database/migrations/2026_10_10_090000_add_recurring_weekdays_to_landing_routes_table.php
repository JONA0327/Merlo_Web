<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a trip be defined by which WEEKDAYS it runs (e.g. "Dom, Lun,
     * Mar") instead of one fixed date — for routes that repeat on a
     * schedule rather than being a one-off. Purely additive: `day` is
     * untouched and every existing trip keeps working exactly as before.
     * A trip uses either `day` (fecha específica, the original mode) or
     * `recurring_weekdays` (new mode), never both — see
     * AdminLandingRouteController::store()/update().
     */
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->json('recurring_weekdays')->nullable()->after('day');
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropColumn('recurring_weekdays');
        });
    }
};
