<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpenseType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'property_id' => Property::factory(),
            'unit_id' => null,
            'document_id' => null,
            'document_analysis_id' => null,
            'date' => $date,
            'amount_ore' => fake()->numberBetween(500, 12_000) * 100,
            'vat_ore' => 0,
            'vendor' => fake()->company(),
            'vendor_orgnr' => fake()->numerify('#########'),
            'category' => fake()->randomElement(config('forvalter.categories')),
            'type' => fake()->randomElement(ExpenseType::cases())->value,
            'description' => fake()->optional()->sentence(4),
            'income_year' => (int) $date->format('Y'),
            'created_by' => null,
        ];
    }

    public function ofType(ExpenseType $type): static
    {
        return $this->state(fn () => ['type' => $type->value]);
    }
}
