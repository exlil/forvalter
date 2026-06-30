<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax constants per income year. These change yearly (mileage rate, rates,
 * thresholds), so they are data, not code (brief §3.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_year_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('mileage_rate_ore_per_km');
            $table->decimal('capital_income_tax_rate', 5, 4);
            $table->unsignedBigInteger('asset_threshold_ore');
            $table->unsignedTinyInteger('business_unit_threshold')->default(5);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_year_settings');
    }
};
