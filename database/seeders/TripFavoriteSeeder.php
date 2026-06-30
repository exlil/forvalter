<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Property;
use App\Models\TripFavorite;
use Illuminate\Database\Seeder;

/**
 * Demo mileage favorites — home → each property with a typical distance.
 * Idempotent (keyed by label) so it can run standalone or via DatabaseSeeder.
 */
class TripFavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $favorites = [
            ['Svaneviksveien 85', 'Befaring', 12],
            ['Blekenberg 36', 'Tilsyn', 8],
            ['Damsgårdsveien 88', 'Visning', 14],
        ];

        foreach ($favorites as [$propertyName, $purpose, $km]) {
            $property = Property::where('name', $propertyName)->first();

            TripFavorite::updateOrCreate(
                ['label' => $propertyName],
                [
                    'property_id' => $property?->id,
                    'distance_km' => $km,
                    'purpose' => $purpose,
                ]
            );
        }
    }
}
