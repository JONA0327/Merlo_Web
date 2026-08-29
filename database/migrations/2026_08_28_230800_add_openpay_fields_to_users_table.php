<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('openpay_customer_id', 64)->nullable()->after('remember_token');
            $table->string('phone', 30)->nullable()->after('openpay_customer_id');

            $table->index('openpay_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['openpay_customer_id']);
            $table->dropColumn(['openpay_customer_id', 'phone']);
        });
    }
};
