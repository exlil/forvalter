<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Tenancy>
 */
class TenancyFactory extends Factory
{
    public function definition(): array
    {
        $rent = fake()->numberBetween(9_000, 18_000);

        return [
            'unit_id' => Unit::factory(),
            'tenant_id' => Tenant::factory(),
            'starts_on' => fake()->dateTimeBetween('-4 years', '-2 months'),
            'ends_on' => null,
            'monthly_rent_ore' => $rent * 100,
            'deposit_ore' => $rent * 3 * 100,
            'notes' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'ends_on' => fake()->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }
}
