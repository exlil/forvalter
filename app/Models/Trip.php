<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TripSource;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kjøring — a mileage trip. The rate is snapshotted and the deduction stored at
 * entry so the figure stays stable across yearly rate changes (brief §3.5, §8).
 */
class Trip extends Model
{
    /** @use HasFactory<\Database\Factories\TripFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'property_id',
        'date',
        'purpose',
        'distance_km',
        'rate_ore_per_km',
        'deduction_ore',
        'source',
        'income_year',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'distance_km' => 'integer',
            'rate_ore_per_km' => 'integer',
            'deduction_ore' => MoneyCast::class,
            'source' => TripSource::class,
            'income_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Trip $trip): void {
            if (empty($trip->income_year) && $trip->date !== null) {
                $trip->income_year = $trip->date->year;
            }

            // Keep the stored deduction consistent with distance × snapshot rate.
            if ($trip->distance_km !== null && $trip->rate_ore_per_km !== null) {
                $trip->deduction_ore = $trip->distance_km * $trip->rate_ore_per_km;
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
