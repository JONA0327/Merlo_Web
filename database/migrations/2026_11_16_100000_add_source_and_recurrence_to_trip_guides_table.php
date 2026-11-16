<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A guide is either MANUAL (made by hand from "Nueva guía") or auto-created for a
// trip (source 'trip'). The two never share a row any more: before, a trip's
// automatic guide was looked up by route (and route+bus for recurring ones), so it
// swallowed a manual guide of the same route/bus — the manual guide vanished from the
// weekly strip and the trip lost its color.
//
// recurring_weekdays: a manual guide that repeats on those weekdays (0=Dom..6=Sáb).
// rolling: such a guide created without an end date keeps extending itself ahead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_guides', function (Blueprint $table) {
            $table->string('source', 10)->default('trip')->after('notes');
            $table->json('recurring_weekdays')->nullable()->after('source');
            $table->boolean('rolling')->default(false)->after('recurring_weekdays');
        });

        // Existing rows: the automatic ones all carry the "Creada automáticamente…" note;
        // anything else came from "Nueva guía" — EXCEPT a guide that a recurring trip is
        // already using today (same route and unit): until now such a trip absorbed it, so
        // it is the trip's guide in practice (its apartados show under the trip's tab).
        // Flipping it to "manual" would split those apartados from the trip, so it keeps
        // behaving as the trip's guide. Guides made from now on are separate.
        $recurring = DB::table('landing_routes')
            ->whereNull('deleted_at')
            ->whereNotNull('recurring_weekdays')
            ->get(['from', 'to', 'bus_unit_id']);

        DB::table('trip_guides')
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', 'Creada autom%'))
            ->get(['id', 'from', 'to', 'bus_unit_id'])
            ->reject(fn ($g) => $recurring->contains(fn ($t) => $t->from === $g->from && $t->to === $g->to && (int) $t->bus_unit_id === (int) $g->bus_unit_id))
            ->each(fn ($g) => DB::table('trip_guides')->where('id', $g->id)->update(['source' => 'manual']));
    }

    public function down(): void
    {
        Schema::table('trip_guides', function (Blueprint $table) {
            $table->dropColumn(['source', 'recurring_weekdays', 'rolling']);
        });
    }
};
