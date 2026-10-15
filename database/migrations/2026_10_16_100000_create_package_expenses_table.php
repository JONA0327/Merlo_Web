<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Egresos del día de paquetería: lo que se entregó/gastó de lo recaudado
    // (concepto y cantidad). Viajan en el corte y se empatan con el ingreso en Control.
    public function up(): void
    {
        Schema::create('package_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('concept');
            $table->decimal('amount', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('package_cortes', function (Blueprint $table) {
            $table->unsignedInteger('expenses_count')->default(0);
            $table->decimal('expenses_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('package_cortes', function (Blueprint $table) {
            $table->dropColumn(['expenses_count', 'expenses_amount']);
        });
        Schema::dropIfExists('package_expenses');
    }
};
