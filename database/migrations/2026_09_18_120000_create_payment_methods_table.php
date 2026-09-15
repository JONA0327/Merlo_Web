<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configured bank accounts shown to customers at checkout
     * while card/OXXO/SPEI (OpenPay) is disabled. More than one can be
     * active at a time (e.g. two different banks).
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('bank_name')->nullable();
            $table->string('beneficiary_name');
            $table->string('clabe', 18)->nullable();
            $table->string('card_number', 19)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
