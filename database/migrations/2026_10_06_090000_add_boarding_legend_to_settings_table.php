<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boarding-point legend shown on tickets / WhatsApp notices — was
     * hardcoded in SeatReservation, moved here so an admin can update
     * the meeting points from Configuraciones without a code deploy.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('boarding_outbound_legend')->nullable();
            $table->string('boarding_return_legend')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['boarding_outbound_legend', 'boarding_return_legend']);
        });
    }
};
