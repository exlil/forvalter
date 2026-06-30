<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->streetName().' '.fake()->buildingNumber(),
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('####'),
            'city' => 'Bergen',
            'property_type' => 'Leilighet',
            'purchase_date' => fake()->dateTimeBetween('-8 years', '-1 year'),
            'purchase_price_ore' => fake()->numberBetween(2_500_000, 6_500_000) * 100,
            'notes' => null,
        ];
    }
}
