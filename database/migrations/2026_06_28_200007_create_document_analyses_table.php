<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-analyse — the extraction tied to a bilag. Stores the raw machine output
 * AND the normalized suggested values together, with the provider/model and
 * prompt/schema versions, so classification quality is traceable and the model
 * is swappable over time (brief §3.4, §7). A human always confirms.
 *
 * confirmed_expense_id is intentionally a plain indexed column (no FK) to avoid
 * a circular constraint with the expenses table, which references this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_version');
            $table->string('schema_version');
            $table->string('status')->default('draft');
            $table->json('raw_output')->nullable();
            $table->json('suggested')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->unsignedBigInteger('confirmed_expense_id')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_analyses');
    }
};
