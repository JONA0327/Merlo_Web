<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site-wide default prices for each ticket type — every new trip
     * copies these into its own trip_ticket_prices rows on creation
     * (see AdminLandingRouteController::store), and LandingRoute::priceFor
     * falls back to these when a trip has no explicit per-trip override.
     * Storing them on the singleton settings row (same row that holds
     * WhatsApp config, Evolution API credentials, etc.) keeps the
     * global-config UI consolidated in /admin/configuraciones + a
     * dedicated "Precios de boleto" section, instead of a new table
     * with its own CRUD.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('default_price_one_way', 10, 2)->nullable()->after('return_resale_validity_hours');
            $table->decimal('default_price_round_trip', 10, 2)->nullable()->after('default_price_one_way');
            $table->decimal('default_price_especial', 10, 2)->nullable()->after('default_price_round_trip');
            $table->decimal('default_price_regreso', 10, 2)->nullable()->after('default_price_especial');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['default_price_one_way', 'default_price_round_trip', 'default_price_especial', 'default_price_regreso']);
        });
    }
};