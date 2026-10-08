<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original (landing_route_id, bus_unit_seat_id) unique index
     * assumed one reservation per seat per trip — true when the trip was
     * modeled as a single leg, but round-trip/especial tickets later grew
     * a RELEASED return leg that can be resold to a different passenger
     * on the same seat (see return_released_at / resold_return_reservation_id).
     * That resale creates a second row for the same seat+trip with
     * leg='return', which the old index rejected outright. Scoping by
     * leg too allows exactly one outbound-leg row and one return-leg row
     * per seat per trip — never two of the same leg.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropUnique(['landing_route_id', 'bus_unit_seat_id']);
            $table->unique(['landing_route_id', 'bus_unit_seat_id', 'leg']);
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropUnique(['landing_route_id', 'bus_unit_seat_id', 'leg']);
            $table->unique(['landing_route_id', 'bus_unit_seat_id']);
        });
    }
};
