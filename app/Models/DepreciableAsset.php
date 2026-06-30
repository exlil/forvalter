<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Driftsmiddel — a depreciable asset with a declining-balance schedule.
 */
class DepreciableAsset extends Model
{
    /** @use HasFactory<\Database\Factories\DepreciableAssetFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'property_id',
        'name',
        'acquired_on',
        'acquisition_cost_ore',
        'depreciation_group',
        'depreciation_rate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'acquisition_cost_ore' => MoneyCast::class,
            'depreciation_rate' => 'decimal:4',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }
}
