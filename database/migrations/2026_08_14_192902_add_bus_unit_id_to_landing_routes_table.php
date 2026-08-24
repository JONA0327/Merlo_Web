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
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->foreignId('bus_unit_id')->nullable()->after('available_seats')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bus_unit_id');
        });
    }
};
