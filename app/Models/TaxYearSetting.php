<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tax constants for a single income year (brief §3.5). Editable data, not code.
 */
class TaxYearSetting extends Model
{
    /** @use HasFactory<\Database\Factories\TaxYearSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'mileage_rate_ore_per_km',
        'capital_income_tax_rate',
        'asset_threshold_ore',
        'business_unit_threshold',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage_rate_ore_per_km' => 'integer',
            'capital_income_tax_rate' => 'decimal:4',
            'asset_threshold_ore' => MoneyCast::class,
            'business_unit_threshold' => 'integer',
        ];
    }

    /**
     * Settings for a year, falling back to config defaults if not yet recorded.
     */
    public static function forYear(int $year): self
    {
        return static::firstOrNew(
            ['year' => $year],
            [
                'mileage_rate_ore_per_km' => config('forvalter.tax_defaults.mileage_rate_ore_per_km'),
                'capital_income_tax_rate' => config('forvalter.tax_defaults.capital_income_tax_rate'),
                'asset_threshold_ore' => config('forvalter.tax_defaults.asset_threshold_ore'),
                'business_unit_threshold' => config('forvalter.tax_defaults.business_unit_threshold'),
            ]
        );
    }
}
