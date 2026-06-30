<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eiendom — a property. A standalone apartment is a property with one unit;
 * a bygård is a property with many. Purchase price is the basis for the
 * derived cost basis (inngangsverdi) — brief §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('postal_code', 4)->nullable();
            $table->string('city')->nullable();
            $table->string('property_type')->nullable();
            $table->date('purchase_date')->nullable();
            $table->bigInteger('purchase_price_ore')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
