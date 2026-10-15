<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Corte (cierre) por viaje/día: foto del desglose de asientos y dinero
    // que se envía a Control Reportes.
    public function up(): void
    {
        Schema::create('trip_cortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_route_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('total_seats');
            $table->unsignedInteger('sold_seats');
            $table->unsignedInteger('late_sold_seats');
            $table->unsignedInteger('apartado_seats');
            $table->unsignedInteger('free_seats');
            $table->decimal('sold_amount', 12, 2);
            $table->decimal('late_amount', 12, 2);
            $table->decimal('abonos_amount', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->unsignedBigInteger('contrato_id')->nullable(); // contrato "compras" en Control
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_cortes');
    }
};
