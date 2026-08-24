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
        Schema::table('bus_units', function (Blueprint $table) {
            $table->string('background_image')->nullable()->after('description');
            $table->boolean('has_upper_deck')->default(false)->after('background_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bus_units', function (Blueprint $table) {
            $table->dropColumn(['background_image', 'has_upper_deck']);
        });
    }
};
