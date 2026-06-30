<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leieforhold — a tenancy linking a tenant to a unit over a period, with rent
 * and deposit. Periods enable pro-rata apportionment (forholdsberegning) when
 * a unit was let only part of the year (brief §5, §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->bigInteger('monthly_rent_ore');
            $table->bigInteger('deposit_ore')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancies');
    }
};
