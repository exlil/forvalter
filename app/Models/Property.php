<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ExpenseType;
use App\Models\Concerns\RecordsActivity;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eiendom — a property holding one or more units.
 */
class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'postal_code',
        'city',
        'property_type',
        'purchase_date',
        'purchase_price_ore',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price_ore' => MoneyCast::class,
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function depreciableAssets(): HasMany
    {
        return $this->hasMany(DepreciableAsset::class);
    }

    /**
     * Cost basis (inngangsverdi) is derived, never stored: purchase price plus
     * the sum of all capitalised improvements (påkostning) — brief §5, §3.7.
     */
    public function costBasis(): Money
    {
        $improvements = $this->expenses()
            ->where('type', ExpenseType::Improvement->value)
            ->sum('amount_ore');

        return new Money(($this->purchase_price_ore?->ore ?? 0) + (int) $improvements);
    }

    /** A property with more than one unit is a bygård; one unit is frittstående. */
    public function isBuilding(): bool
    {
        return ($this->units_count ?? $this->units()->count()) > 1;
    }

    /** Sum of current monthly rent across the property's units (samlet leie). */
    public function monthlyRent(): Money
    {
        return new Money((int) $this->units->sum(
            fn (Unit $unit) => $unit->currentTenancy?->monthly_rent_ore?->ore ?? 0
        ));
    }

    /** Felleskostnader — property-level expenses with no unit (brief: held at building level). */
    public function felleskostnader(): HasMany
    {
        return $this->hasMany(Expense::class)->whereNull('unit_id');
    }

    public function felleskostnaderForMonth(int $year, int $month): Money
    {
        return new Money((int) $this->felleskostnader()
            ->whereYear('date', $year)->whereMonth('date', $month)->sum('amount_ore'));
    }

    public function felleskostnaderForYear(int $year): Money
    {
        return new Money((int) $this->felleskostnader()->where('income_year', $year)->sum('amount_ore'));
    }
}
