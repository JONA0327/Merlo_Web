<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Otros conceptos del corte de un viaje (gastos e ingresos del día) y el efectivo capturado a mano.
    public function up(): void
    {
        Schema::create('trip_corte_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_route_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // gasto | ingreso
            $table->string('concept');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('amount', 12, 2); // cantidad × monto unitario
            $table->string('note')->nullable();
            $table->boolean('counts')->default(true); // false = solo informativo (no entra a Ingresos/Egresos)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('trip_cortes', function (Blueprint $table) {
            $table->decimal('cash_amount', 12, 2)->nullable();
            $table->string('lines_signature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_cortes', function (Blueprint $table) {
            $table->dropColumn(['cash_amount', 'lines_signature']);
        });
        Schema::dropIfExists('trip_corte_lines');
    }
};
