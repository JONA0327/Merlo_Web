<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the background_image column that used to hold a per-unit floor
     * plan ("plano") image. The feature is no longer surfaced in the admin
     * editor and the column is dead weight on the bus_units table — keeping
     * it around would just hide stale disk references (Storage::disk
     * entries that nothing reads anymore) and risk confusing the next
     * person who looks at the schema.
     */
    public function up(): void
    {
        Schema::table('bus_units', function (Blueprint $table) {
            $table->dropColumn('background_image');
        });
    }

    public function down(): void
    {
        Schema::table('bus_units', function (Blueprint $table) {
            $table->string('background_image')->nullable()->after('description');
        });
    }
};
