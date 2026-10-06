<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master list of cities the company serves — both as origin ("from")
     * and as destination ("to"). Pulled out of free-text inputs on the
     * trip form so the admin declares them once and every new trip
     * picks from the same canonical list (no more "Ciudad de México"
     * vs "CDMX" vs "ciudad de mexico" for the same place).
     *
     * Seeded separately by /admin/destinations after deploy. The trip
     * table itself still carries free-text `from` / `to` columns for
     * historical rows (the FK isn't enforced), but the create/edit
     * trip forms will render a <select> with these destinations.
     */
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};