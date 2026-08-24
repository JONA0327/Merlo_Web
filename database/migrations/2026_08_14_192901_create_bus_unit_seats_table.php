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
        Schema::create('bus_unit_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_unit_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->default('normal');
            $table->float('pos_x');
            $table->float('pos_y');
            $table->timestamps();

            $table->unique(['bus_unit_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bus_unit_seats');
    }
};
