<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driftsmiddel — a depreciable movable asset above the yearly threshold, with
 * its declining-balance schedule (saldoavskrivning) per property (brief §5, §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciable_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('acquired_on');
            $table->bigInteger('acquisition_cost_ore');
            $table->string('depreciation_group')->nullable();
            $table->decimal('depreciation_rate', 5, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciable_assets');
    }
};
