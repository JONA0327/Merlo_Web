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
            $table->string('kind')->default('seat')->after('bus_unit_id');
            $table->unsignedInteger('corner_radius')->default(8)->after('type');
            $table->unsignedInteger('border_width')->default(2)->after('corner_radius');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->dropColumn(['kind', 'corner_radius', 'border_width']);
        });
    }
};
