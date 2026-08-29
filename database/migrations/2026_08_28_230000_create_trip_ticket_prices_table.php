<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Holds the per-trip prices for each ticket type (one-way vs
     * round-trip). Pulled out of the landing_routes table so the admin
     * can change prices in one screen without opening every trip, and
     * so the customer-facing price is never out of sync between
     * "Desde" and the checkout total.
     */
    public function up(): void
    {
        Schema::create('trip_ticket_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_route_id')->constrained()->cascadeOnDelete();
            $table->string('trip_type', 20); // 'one_way' | 'round_trip'
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['landing_route_id', 'trip_type']);
        });

        // Backfill: take whatever was stored on the trip's old
        // ticket_price / price column and seed it as the one-way price.
        // Round-trip is left null — the admin sets it on the new
        // "Precios de boleto" screen.
        $trips = DB::table('landing_routes')->get(['id', 'ticket_price', 'price']);
        foreach ($trips as $trip) {
            $raw = $trip->ticket_price ?? $trip->price;
            if (! $raw) continue;
            $numeric = (float) preg_replace('/[^0-9.]/', '', $raw);
            if ($numeric <= 0) continue;

            DB::table('trip_ticket_prices')->insert([
                'landing_route_id' => $trip->id,
                'trip_type' => 'one_way',
                'price' => $numeric,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_ticket_prices');
    }
};
