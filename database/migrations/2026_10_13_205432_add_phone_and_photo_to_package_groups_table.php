<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The new registration form no longer collects an email (WhatsApp
     * replaces the tracking email) and the total is optional, so both
     * columns that used to be required on a group have to allow null.
     */
    public function up(): void
    {
        Schema::table('package_groups', function (Blueprint $table) {
            $table->string('client_email')->nullable()->change();
            $table->decimal('total_price', 10, 2)->nullable()->change();
            $table->string('phone')->nullable()->after('client_email');
            $table->string('photo_path')->nullable()->after('tracking_code');
        });
    }

    public function down(): void
    {
        Schema::table('package_groups', function (Blueprint $table) {
            $table->dropColumn(['phone', 'photo_path']);
            $table->decimal('total_price', 10, 2)->nullable(false)->change();
            $table->string('client_email')->nullable(false)->change();
        });
    }
};
