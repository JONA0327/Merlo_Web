<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer-initiated return-date change: the passenger marks that
     * they won't make their scheduled return, gets a fixed 5-business-day
     * window to pick a replacement date among already-published trips,
     * and either picks one (return_changed_to_reservation_id) or the
     * window lapses and the return leg is voided (return_voided_at).
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->timestamp('return_change_requested_at')->nullable()->after('return_resale_expires_at');
            $table->timestamp('return_change_deadline')->nullable()->after('return_change_requested_at');
            $table->foreignId('return_changed_to_reservation_id')->nullable()->after('return_change_deadline')
                ->constrained('seat_reservations')->nullOnDelete();
            $table->timestamp('return_voided_at')->nullable()->after('return_changed_to_reservation_id');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_changed_to_reservation_id');
            $table->dropColumn(['return_change_requested_at', 'return_change_deadline', 'return_voided_at']);
        });
    }
};
