<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support reselling the unused return leg of a round-trip
     * reservation to a different customer.
     *
     *   - leg: for trip_type = 'one_way' rows, whether this ticket
     *     covers the outbound calendar leg (normal purchase) or the
     *     return calendar leg (a resold return). Round-trip rows
     *     cover both legs regardless of this column.
     *
     *   - return_released_at / _by: stamped when an admin explicitly
     *     frees up the return leg of a round-trip reservation for
     *     resale — never inferred automatically.
     *
     *   - return_resale_expires_at: return_released_at + the
     *     configured validity window, frozen at release time so a
     *     later change to the global setting doesn't reach back into
     *     releases already in progress.
     *
     *   - resold_return_reservation_id: set on the ORIGINAL row once
     *     someone buys the released return leg, pointing at the new
     *     reservation. Prevents double-selling the same return leg.
     *
     *   - source_reservation_id: set on the NEW (resale) row,
     *     pointing back at the original round-trip reservation it
     *     was carved from.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('leg', 10)->default('outbound')->after('trip_type');
            $table->timestamp('return_released_at')->nullable()->after('return_verified_by');
            $table->foreignId('return_released_by')->nullable()->after('return_released_at')->constrained('users')->nullOnDelete();
            $table->timestamp('return_resale_expires_at')->nullable()->after('return_released_by');
            $table->foreignId('resold_return_reservation_id')->nullable()->after('return_resale_expires_at')->constrained('seat_reservations')->nullOnDelete();
            $table->foreignId('source_reservation_id')->nullable()->after('resold_return_reservation_id')->constrained('seat_reservations')->nullOnDelete();

            $table->index('return_released_at');
            $table->index('return_resale_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropIndex(['return_resale_expires_at']);
            $table->dropIndex(['return_released_at']);
            $table->dropForeign(['source_reservation_id']);
            $table->dropForeign(['resold_return_reservation_id']);
            $table->dropForeign(['return_released_by']);
            $table->dropColumn([
                'leg',
                'return_released_at',
                'return_released_by',
                'return_resale_expires_at',
                'resold_return_reservation_id',
                'source_reservation_id',
            ]);
        });
    }
};
