<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            // Null means "use the global default from Configuraciones" —
            // same override/fallback shape as this trip's own prices.
            $table->text('boarding_outbound_legend')->nullable()->after('image');
            $table->text('boarding_return_legend')->nullable()->after('boarding_outbound_legend');
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropColumn(['boarding_outbound_legend', 'boarding_return_legend']);
        });
    }
};
