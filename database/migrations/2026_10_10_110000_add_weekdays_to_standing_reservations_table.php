<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "De planta" isn't "this seat goes on every single run of this
     * route" — it's "this seat goes every [weekday]". Nullable/empty
     * means every day (the original, only behavior before this column
     * existed), so every existing row keeps working unchanged.
     */
    public function up(): void
    {
        Schema::table('standing_reservations', function (Blueprint $table) {
            $table->json('weekdays')->nullable()->after('trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('standing_reservations', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }
};
