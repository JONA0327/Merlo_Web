<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Never actually used by the app — every trip card/boleto/email just
     * showed whatever free-text string the admin typed (or nothing).
     * Dropped at the admin's request.
     */
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->string('duration')->nullable()->after('day');
        });
    }
};
