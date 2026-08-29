<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend seat_reservations so the admin can "apartar" seats for a
     * specific client (think: over-the-counter or phone reservation)
     * without that client needing a User account on the site.
     *
     * The original table only tracked a landing_route + seat + user_id
     * tuple, which assumed a logged-in customer making their own
     * reservation. With this migration we add:
     *   - customer_name / customer_email : the contact the admin typed in
     *   - status                         : pending → sent (and later
     *                                     "paid" once payment is wired)
     *   - reserved_by                    : which admin created the apartado
     *   - ticket_sent_at                 : when the "Enviar boleto" button
     *                                     was clicked (drives the email)
     *   - notes                          : free-form memo
     *
     * user_id is intentionally LEFT as NOT NULL here — the schema
     * change to make it nullable for admin apartados is in a follow-up
     * migration (2026_08_29_103508_make_user_id_nullable_on_seat_reservations_table).
     * Until that migration runs, AdminSeatReservationController::store()
     * will fail with "NOT NULL constraint failed: user_id" the first
     * time anyone tries to create an apartado for a non-registered
     * customer. Don't reorder these two migrations.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('status', 20)->default('pending')->after('customer_email');
            $table->foreignId('reserved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('ticket_sent_at')->nullable()->after('reserved_by');
            $table->text('notes')->nullable()->after('ticket_sent_at');

            $table->index(['landing_route_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropIndex(['landing_route_id', 'status']);
            $table->dropForeign(['reserved_by']);
            $table->dropColumn(['customer_name', 'customer_email', 'status', 'reserved_by', 'ticket_sent_at', 'notes']);
        });
    }
};
