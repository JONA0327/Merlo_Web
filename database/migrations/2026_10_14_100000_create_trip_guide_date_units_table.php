<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A guide spans many days but not every day runs the same bus: a row here
// overrides the guide's default bus_unit_id for that one date.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_guide_date_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_guide_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('bus_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trip_guide_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_guide_date_units');
    }
};
