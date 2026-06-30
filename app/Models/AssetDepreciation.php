<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One income year of a depreciable asset's declining-balance schedule.
 */
class AssetDepreciation extends Model
{
    /** @use HasFactory<\Database\Factories\AssetDepreciationFactory> */
    use HasFactory;

    protected $fillable = [
        'depreciable_asset_id',
        'income_year',
        'opening_balance_ore',
        'depreciation_ore',
        'closing_balance_ore',
    ];

    protected function casts(): array
    {
        return [
            'income_year' => 'integer',
            'opening_balance_ore' => MoneyCast::class,
            'depreciation_ore' => MoneyCast::class,
            'closing_balance_ore' => MoneyCast::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(DepreciableAsset::class, 'depreciable_asset_id');
    }
}
