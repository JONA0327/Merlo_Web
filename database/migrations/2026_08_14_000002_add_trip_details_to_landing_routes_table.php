<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->string('departure_time')->nullable()->after('duration');
            $table->unsignedInteger('available_seats')->default(0)->after('departure_time');
            $table->string('ticket_price')->nullable()->after('available_seats');
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropColumn(['departure_time', 'available_seats', 'ticket_price']);
        });
    }
};
