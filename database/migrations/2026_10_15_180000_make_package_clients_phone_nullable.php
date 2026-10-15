<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Clientes de paquetes ya entregados y registrados después, sin folio ni teléfono.
    public function up(): void
    {
        Schema::table('package_clients', function (Blueprint $table) {
            $table->string('phone', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('package_clients', function (Blueprint $table) {
            $table->string('phone', 10)->nullable(false)->change();
        });
    }
};
