<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilag — the uploaded receipt/invoice. The canonical, immutable source of
 * truth that an expense references and an AI analysis attaches to (brief §3.2).
 * The hash supports duplicate detection (brief §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('receipt');
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('hash', 64)->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
