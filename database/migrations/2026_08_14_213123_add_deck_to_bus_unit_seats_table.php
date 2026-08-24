<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->string('deck')->default('lower')->after('bus_unit_id');
            $table->dropUnique(['bus_unit_id', 'label']);
            $table->unique(['bus_unit_id', 'deck', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->dropUnique(['bus_unit_id', 'deck', 'label']);
            $table->unique(['bus_unit_id', 'label']);
            $table->dropColumn('deck');
        });
    }
};
