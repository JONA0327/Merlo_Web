<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the separate "day before travel" WhatsApp pass (ticket for
     * paid/standing passengers, payment reminder for unpaid ones) — kept
     * apart from ticket_sent_at, which already means something else now
     * that a Guía apartado gets an immediate reservation-confirmation
     * text of its own (see AdminTripGuideController::storeReservation()).
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('ticket_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
