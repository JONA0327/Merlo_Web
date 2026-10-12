<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('boarding_return_legend');
        });

        // The CHICONCUAC → CDMX route used to be highlighted by a hard-coded
        // name check in the guides view; keep it orange now that it's a setting.
        DB::table('landing_routes')
            ->where('from', 'like', '%CHICONCUAC%')->orWhere('to', 'like', '%CHICONCUAC%')
            ->update(['color' => 'orange']);
    }

    public function down(): void
    {
        Schema::table('landing_routes', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
