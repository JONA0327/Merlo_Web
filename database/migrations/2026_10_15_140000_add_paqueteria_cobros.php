<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Paquetería: clientes (por teléfono), cobros parciales/pendientes por envío,
    // evidencia de entrega y corte diario que se manda a Control como ingreso.
    public function up(): void
    {
        Schema::create('package_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 10)->unique(); // últimos 10 dígitos
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('package_client_id')->nullable()->constrained('package_clients')->nullOnDelete();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('delivery_photo_path')->nullable();
        });

        Schema::table('package_groups', function (Blueprint $table) {
            $table->foreignId('package_client_id')->nullable()->constrained('package_clients')->nullOnDelete();
            $table->decimal('amount_paid', 10, 2)->default(0);
        });

        Schema::create('package_payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // Package suelto o PackageGroup
            $table->decimal('amount', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
        });

        Schema::create('package_cortes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->unsignedInteger('payments_count');
            $table->decimal('total_amount', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Lo que ya existía se considera cobrado: así no aparece como pendiente
        // y tampoco genera ingreso en el corte (no hay renglón en package_payments).
        DB::table('packages')->whereNotNull('price')->update(['amount_paid' => DB::raw('price')]);
        DB::table('package_groups')->whereNotNull('total_price')->update(['amount_paid' => DB::raw('total_price')]);

        $clientIds = [];
        foreach (['packages' => DB::table('packages')->whereNull('package_group_id'), 'package_groups' => DB::table('package_groups')] as $table => $query) {
            foreach ($query->whereNotNull('phone')->get(['id', 'client_name', 'phone']) as $row) {
                $digits = substr(preg_replace('/\D/', '', $row->phone), -10);
                if (strlen($digits) < 10) {
                    continue;
                }
                $clientIds[$digits] ??= DB::table('package_clients')->insertGetId([
                    'name' => $row->client_name ?? 'Sin nombre',
                    'phone' => $digits,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table($table)->where('id', $row->id)->update(['package_client_id' => $clientIds[$digits]]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_cortes');
        Schema::dropIfExists('package_payments');
        Schema::table('package_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_client_id');
            $table->dropColumn('amount_paid');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_client_id');
            $table->dropColumn(['amount_paid', 'delivery_photo_path']);
        });
        Schema::dropIfExists('package_clients');
    }
};
