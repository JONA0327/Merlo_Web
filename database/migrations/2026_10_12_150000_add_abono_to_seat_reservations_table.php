<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Partial payment (abono) for the whole apartado, stored on the root row.
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->decimal('abono', 10, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropColumn('abono');
        });
    }
};
