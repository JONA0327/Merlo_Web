<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wire the apartado (and any future client purchase) up to the
     * operator's QR check-in flow.
     *
     *   - ticket_code: 32-char random token. This is what the QR
     *     encodes and what the operator types/scans at the door.
     *     Unique, indexed, long enough to be unforgeable. A fake
     *     code won't match any row, so the system rejects it.
     *
     *   - outbound_verified_at / _by: stamped the first time the
     *     operator scans the QR on the way OUT (the only leg for
     *     a one-way ticket).
     *
     *   - return_verified_at / _by: stamped the second time the
     *     operator scans the QR on the way BACK. Only meaningful
     *     for round-trip tickets (trip_type = 'round_trip') but we
     *     keep the column on every row to avoid nullable gymnastics
     *     in the admin UI.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('ticket_code', 48)->nullable()->after('notes')->unique();
            $table->timestamp('outbound_verified_at')->nullable()->after('ticket_code');
            $table->foreignId('outbound_verified_by')->nullable()->after('outbound_verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('return_verified_at')->nullable()->after('outbound_verified_by');
            $table->foreignId('return_verified_by')->nullable()->after('return_verified_at')->constrained('users')->nullOnDelete();

            $table->index('outbound_verified_at');
            $table->index('return_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropIndex(['return_verified_at']);
            $table->dropIndex(['outbound_verified_at']);
            $table->dropForeign(['return_verified_by']);
            $table->dropForeign(['outbound_verified_by']);
            $table->dropColumn(['ticket_code', 'outbound_verified_at', 'outbound_verified_by', 'return_verified_at', 'return_verified_by']);
        });
    }
};
