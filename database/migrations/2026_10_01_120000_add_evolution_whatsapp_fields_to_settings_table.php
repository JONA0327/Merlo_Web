<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evolution API (self-hosted WhatsApp Web wrapper) connection details
     * — admin-editable from /admin/whatsapp instead of living only in
     * .env, so a superadmin can create/reconnect the instance and scan
     * its QR code without needing a code deploy.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('evolution_api_url')->nullable();
            $table->string('evolution_api_key')->nullable();
            $table->string('evolution_instance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['evolution_api_url', 'evolution_api_key', 'evolution_instance']);
        });
    }
};
