<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxYearSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds tax constants per income year. Values are indicative and MUST be
 * verified against Skatteetaten before any filing (brief §6).
 */
class TaxYearSeeder extends Seeder
{
    public function run(): void
    {
        $years = [
            2025 => ['mileage' => 350, 'rate' => 0.22, 'threshold' => 3_000_000],
            2026 => ['mileage' => 350, 'rate' => 0.22, 'threshold' => 3_000_000],
        ];

        foreach ($years as $year => $cfg) {
            TaxYearSetting::updateOrCreate(['year' => $year], [
                'mileage_rate_ore_per_km' => $cfg['mileage'],
                'capital_income_tax_rate' => $cfg['rate'],
                'asset_threshold_ore' => $cfg['threshold'],
                'business_unit_threshold' => 5,
                'notes' => 'Seed-verdi — må verifiseres mot Skatteetaten.',
            ]);
        }
    }
}
