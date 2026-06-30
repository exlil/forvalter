<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lån — a loan per property. Interest is deductible (recorded as finance
 * expenses); principal is not. The two are kept separate (brief §5). Detailed
 * amortisation arrives in Phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('lender');
            $table->bigInteger('original_principal_ore')->nullable();
            $table->bigInteger('current_balance_ore')->nullable();
            $table->decimal('interest_rate', 5, 3)->nullable();
            $table->date('started_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
