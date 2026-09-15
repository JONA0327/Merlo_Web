<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support the manual bank-transfer checkout: a unique reference the
     * customer must write as the transfer's "concepto" (see
     * SeatReservation::generateTransferReference()), the proof-of-payment
     * file they upload, and when the pending hold expires if nobody
     * validates it.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('transfer_reference')->nullable()->unique()->after('device_fingerprint');
            $table->string('transfer_proof_path')->nullable()->after('transfer_reference');
            $table->timestamp('transfer_expires_at')->nullable()->after('transfer_proof_path');

            $table->index('transfer_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropIndex(['transfer_expires_at']);
            $table->dropColumn(['transfer_reference', 'transfer_proof_path', 'transfer_expires_at']);
        });
    }
};
