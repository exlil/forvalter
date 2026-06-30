<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inntekt — rent per unit per period, mirrored in for yield analysis (brief §5).
 * A null received_on means the rent is still outstanding (utestående leie).
 * Deposits are NOT income — they are tracked as balances on the tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenancy_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->bigInteger('amount_ore');
            $table->date('received_on')->nullable();
            $table->unsignedSmallInteger('income_year');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'period_year', 'period_month']);
            $table->index('income_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
