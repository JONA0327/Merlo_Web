<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "De planta" — a seat permanently assigned to the same person on a
     * given route, so the admin doesn't have to re-register them every
     * time a new trip opens for it (e.g. a staff member who always rides
     * that run). Applied automatically by StandingReservation::applyToTrip()
     * whenever a matching trip is created.
     */
    public function up(): void
    {
        Schema::create('standing_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('from');
            $table->string('to');
            $table->foreignId('bus_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_unit_seat_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('trip_type')->default('one_way');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from', 'to', 'bus_unit_id', 'bus_unit_seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_reservations');
    }
};
