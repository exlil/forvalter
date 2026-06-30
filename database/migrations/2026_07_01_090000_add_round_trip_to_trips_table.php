<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tur/retur flag on a trip. When set, distance_km already holds the full
 * there-and-back distance; the flag is kept so the UI can show "t/r" and so a
 * round trip can be corroborated against ~2 bompasseringer when matching tolls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('round_trip')->default(false)->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('round_trip');
        });
    }
};
