<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hold seats for unauthenticated guests so two browsers fighting
     * over the same seat don't both pass the transactional "is it still
     * available?" check at submit time. The Laravel session cookie
     * (set on every first response) is good enough as a holder id —
     * long-lived across tabs in the same browser, unique enough per
     * visitor that two guests colliding on the same session id is
     * effectively impossible.
     *
     * user_id stays on the row but goes nullable (with its FK relaxed
     * from cascade-delete to null-on-delete) so a single schema holds
     * both kinds of holder.
     */
    public function up(): void
    {
        // SQLite has no `ALTER CONSTRAINT` / `ALTER FOREIGN KEY` syntax;
        // rebuild the table in one go. We do it with raw DDL so we
        // don't need doctrine/dbal to mutate existing columns.
        Schema::drop('seat_holds');
        Schema::create('seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_unit_seat_id')->constrained()->cascadeOnDelete();
            // Exactly one of user_id (logged-in) or session_id (guest)
            // is populated — the seat_holds.holder_id helper synthesizes
            // a comparable string for the JS side.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 128)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['landing_route_id', 'bus_unit_seat_id']);
            $table->index('expires_at');
            $table->index('user_id');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_holds');
        Schema::create('seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_unit_seat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['landing_route_id', 'bus_unit_seat_id']);
            $table->index('expires_at');
        });
    }
};