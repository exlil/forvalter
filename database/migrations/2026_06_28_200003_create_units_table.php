<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boenhet — a unit within a property. The level at which occupancy, rent and
 * per-unit cost allocation happen (brief §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable(); // bruksenhetsnummer, e.g. H0101 (buildings)
            $table->string('unit_type')->nullable();
            $table->unsignedInteger('area_sqm')->nullable();
            $table->decimal('rooms', 3, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
