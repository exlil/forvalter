<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TripSource;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', 'now');
        $km = fake()->numberBetween(5, 60);
        $rate = config('forvalter.tax_defaults.mileage_rate_ore_per_km');

        return [
            'property_id' => Property::factory(),
            'date' => $date,
            'purpose' => fake()->randomElement(['Befaring', 'Visning', 'Henting materialer', 'Møte med leietaker']),
            'distance_km' => $km,
            'rate_ore_per_km' => $rate,
            'deduction_ore' => $km * $rate,
            'source' => TripSource::Web->value,
            'income_year' => (int) $date->format('Y'),
            'created_by' => null,
            'notes' => null,
        ];
    }
}
