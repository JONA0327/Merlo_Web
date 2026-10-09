<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "No viaja hoy" — an admin releasing a standing ("de planta") seat
     * for ONE specific trip only (the regular passenger isn't coming that
     * day), without touching the standing assignment itself — every OTHER
     * matching trip still auto-books it as usual. See
     * StandingReservation::applyToTrip() and
     * AdminSeatReservationController::releaseStanding().
     */
    public function up(): void
    {
        Schema::create('standing_reservation_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standing_reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_route_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['standing_reservation_id', 'landing_route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_reservation_skips');
    }
};
