<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        $rooms = fake()->numberBetween(1, 5);

        return [
            'property_id' => Property::factory(),
            'name' => fake()->streetName().' '.fake()->buildingNumber(),
            'unit_type' => $rooms.'-roms',
            'area_sqm' => fake()->numberBetween(35, 110),
            'rooms' => $rooms,
            'notes' => null,
        ];
    }
}
