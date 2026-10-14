<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row counter for the global, never-resetting package folio
     * series (F-000001, F-000002, ...). PackageFolioSequence::reserve()
     * locks this row to hand out contiguous ranges atomically.
     */
    public function up(): void
    {
        Schema::create('package_folio_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('next_value')->default(1);
            $table->timestamps();
        });

        DB::table('package_folio_sequences')->insert(['id' => 1, 'next_value' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('package_folio_sequences');
    }
};
