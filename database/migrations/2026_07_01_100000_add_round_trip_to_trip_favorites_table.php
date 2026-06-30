<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember the tur/retur choice on a saved favorite route, so tapping it
 * restores the round-trip toggle along with the leg distance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_favorites', function (Blueprint $table) {
            $table->boolean('round_trip')->default(false)->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('trip_favorites', function (Blueprint $table) {
            $table->dropColumn('round_trip');
        });
    }
};
