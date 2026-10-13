<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Deleting a dated trip must not cascade away its reservations
    // (the history shown in Apartar asientos) — it's soft-deleted instead.
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
