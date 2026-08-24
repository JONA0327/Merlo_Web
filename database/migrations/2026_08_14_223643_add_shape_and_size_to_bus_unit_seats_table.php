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
            $table->string('shape')->default('rect')->after('kind');
            $table->unsignedInteger('width')->default(40)->after('shape');
            $table->unsignedInteger('height')->default(40)->after('width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bus_unit_seats', function (Blueprint $table) {
            $table->dropColumn(['shape', 'width', 'height']);
        });
    }
};
