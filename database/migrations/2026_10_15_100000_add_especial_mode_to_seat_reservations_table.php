<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An "especial" is charged the same whether it is ida, redondo or regreso, so
// the category (price) stays trip_type='especial' and this column says which
// legs it actually covers: one_way | round_trip | return. NULL = round_trip,
// so every especial that already exists keeps behaving exactly as before.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('especial_mode', 20)->nullable()->after('trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropColumn('especial_mode');
        });
    }
};
