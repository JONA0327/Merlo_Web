<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Asientos vendidos fuera del sistema (taquilla, a mano): se capturan en el corte.
    public function up(): void
    {
        Schema::table('trip_cortes', function (Blueprint $table) {
            $table->unsignedInteger('external_seats')->default(0);
            $table->decimal('external_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('trip_cortes', function (Blueprint $table) {
            $table->dropColumn(['external_seats', 'external_amount']);
        });
    }
};
