<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Income>
 */
class IncomeFactory extends Factory
{
    public function definition(): array
    {
        $year = (int) fake()->dateTimeBetween('-1 year', 'now')->format('Y');
        $month = fake()->numberBetween(1, 12);

        return [
            'unit_id' => Unit::factory(),
            'tenancy_id' => null,
            'period_year' => $year,
            'period_month' => $month,
            'amount_ore' => fake()->numberBetween(9_000, 18_000) * 100,
            'received_on' => fake()->dateTimeBetween('-1 year', 'now'),
            'income_year' => $year,
            'notes' => null,
        ];
    }

    public function outstanding(): static
    {
        return $this->state(fn () => ['received_on' => null]);
    }
}
