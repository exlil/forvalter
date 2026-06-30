<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved mileage routes ("favoritter") for fast logging — typically home → a
 * property with the usual distance. Shared by both owners (everything is 50/50).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_favorites', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('distance_km');
            $table->string('purpose')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_favorites');
    }
};
