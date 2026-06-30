<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kjøring — a mileage trip to a property (brief §5, §8). The rate is snapshotted
 * from the income year's tax settings at entry, and the deductible amount is
 * stored so historical trips stay stable even if the yearly rate changes. A
 * null property_id means a shared/common trip (Felles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('purpose');
            $table->unsignedInteger('distance_km');
            $table->unsignedInteger('rate_ore_per_km');
            $table->bigInteger('deduction_ore');
            $table->string('source')->default('web');
            $table->unsignedSmallInteger('income_year');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('income_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
