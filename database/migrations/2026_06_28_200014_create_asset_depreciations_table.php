<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-income-year declining-balance entry for a depreciable asset. The year's
 * depreciation is surfaced as a deductible line; the closing balance carries
 * forward (brief §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depreciable_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('income_year');
            $table->bigInteger('opening_balance_ore');
            $table->bigInteger('depreciation_ore');
            $table->bigInteger('closing_balance_ore');
            $table->timestamps();

            $table->unique(['depreciable_asset_id', 'income_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
    }
};
