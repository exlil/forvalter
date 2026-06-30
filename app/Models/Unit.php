<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Boenhet — a unit within a property; the level at which occupancy, rent and
 * per-unit cost allocation happen.
 */
class Unit extends Model
{
    /** @use HasFactory<\Database\Factories\UnitFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'property_id',
        'name',
        'code',
        'unit_type',
        'area_sqm',
        'rooms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_sqm' => 'integer',
            'rooms' => 'float',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }

    public function currentTenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class)->latestOfMany('starts_on');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Occupancy status, derived from the current tenancy and rent balances —
     * never stored as primary truth (brief §3.7).
     */
    public function status(): UnitStatus
    {
        $tenancy = $this->currentTenancy;
        $isActive = $tenancy !== null
            && ($tenancy->ends_on === null || ! $tenancy->ends_on->isPast());

        if (! $isActive) {
            return UnitStatus::Vacant;
        }

        return $this->hasOutstandingRent() ? UnitStatus::Arrears : UnitStatus::Rented;
    }

    public function hasOutstandingRent(): bool
    {
        return $this->incomes()->whereNull('received_on')->exists();
    }
}
