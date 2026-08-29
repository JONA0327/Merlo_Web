<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The apartado flow (AdminSeatReservationController::store) creates
 * SeatReservation rows for walk-in / phone customers who don't have a
 * User account on the site, so user_id legitimately has to be NULL.
 *
 * The original create_seat_reservations migration declared user_id as
 * a non-nullable foreign key, which broke the admin apartado insert
 * with:
 *   SQLSTATE[23000]: NOT NULL constraint failed: seat_reservations.user_id
 *
 * We also rebuild the FK with nullOnDelete() so deleting a user doesn't
 * wipe out historical client-purchase rows that happened to point at
 * them (defensive — the table is also a financial record).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing FK first so we can change the column type.
        // On SQLite the Schema builder will recreate the table under
        // the hood; on MySQL/Postgres it's a plain ALTER.
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Refuse to roll back: existing apartados have user_id = NULL
        // and a non-nullable column would lose data. Force the operator
        // to fix the data first if they really need to revert.
        if (DB::table('seat_reservations')->whereNull('user_id')->exists()) {
            throw new \RuntimeException(
                'Cannot make seat_reservations.user_id NOT NULL while rows with NULL user_id exist. '
                .'Reassign or delete those rows first.'
            );
        }

        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }
};
