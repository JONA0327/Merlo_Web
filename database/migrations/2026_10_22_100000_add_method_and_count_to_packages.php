<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->after('amount');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('package_count')->default(1)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('package_payments', fn (Blueprint $table) => $table->dropColumn('method'));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('package_count'));
    }
};
