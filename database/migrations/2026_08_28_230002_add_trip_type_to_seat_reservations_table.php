<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each reservation now records which type of ticket it was sold
     * for (one-way vs round-trip) and the unit price at the moment of
     * purchase.
     *
     * unit_price is captured at sale time so future price changes
     * don't retroactively rewrite the historical receipt — the
     * customer's "Yo pagué $X" still matches the row a year later.
     *
     * Legacy rows (created before this migration) are stamped as
     * one-way at the trip's current price so old reports keep making
     * sense.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('trip_type', 20)->default('one_way')->after('user_id');
            $table->decimal('unit_price', 10, 2)->nullable()->after('trip_type');
            $table->index('trip_type');
        });

        // Backfill unit_price from the trip's current one-way price so
        // existing rows have a sensible historical price.
        $rows = DB::table('seat_reservations')
            ->join('trip_ticket_prices', function ($j) {
                $j->on('trip_ticket_prices.landing_route_id', '=', 'seat_reservations.landing_route_id')
                    ->where('trip_ticket_prices.trip_type', '=', 'one_way');
            })
            ->whereNull('seat_reservations.unit_price')
            ->get(['seat_reservations.id', 'trip_ticket_prices.price']);

        foreach ($rows as $row) {
            DB::table('seat_reservations')
                ->where('id', $row->id)
                ->update(['unit_price' => $row->price]);
        }
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropIndex(['trip_type']);
            $table->dropColumn(['trip_type', 'unit_price']);
        });
    }
};
