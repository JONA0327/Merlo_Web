<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'default_price_one_way',
                'default_price_round_trip',
                'default_price_especial',
                'default_price_regreso',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('default_price_one_way', 10, 2)->nullable();
            $table->decimal('default_price_round_trip', 10, 2)->nullable();
            $table->decimal('default_price_especial', 10, 2)->nullable();
            $table->decimal('default_price_regreso', 10, 2)->nullable();
        });
    }
};
