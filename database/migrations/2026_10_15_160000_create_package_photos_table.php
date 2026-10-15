<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A registration can carry several evidence photos. The first one stays in
// packages.photo_path / package_groups.photo_path (so everything that already
// reads it keeps working); the extra ones live here, attached to the lone
// package or to the group when several packages were bundled.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('package_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_photos');
    }
};
