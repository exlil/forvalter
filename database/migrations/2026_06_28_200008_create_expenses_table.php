<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostnad — an expense. Carries its tax treatment (`type` = kostnadstype) from
 * the moment it is recorded; classification is never inferred later (brief §3.3,
 * §4). `category` is the separate, descriptive grouping. Money is integer øre.
 * MVA is stored but not handled in v1 (residential rental is exempt — brief §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->bigInteger('amount_ore');
            $table->bigInteger('vat_ore')->default(0);
            $table->string('vendor')->nullable();
            $table->string('vendor_orgnr', 9)->nullable();
            $table->string('category')->nullable();
            $table->string('type'); // App\Enums\ExpenseType — required tax axis
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('income_year');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['income_year', 'type']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
