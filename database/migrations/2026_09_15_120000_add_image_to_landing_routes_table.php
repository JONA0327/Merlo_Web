<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional admin-uploaded photo for a route, stored on the "public"
     * disk. Shown on the landing page card instead of the generic
     * bus-icon graphic when set.
     */
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->string('image')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
