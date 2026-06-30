<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inntekt — rent for a unit in a given month. A null received_on means the rent
 * is still outstanding. Deposits are not income (brief §5).
 */
class Income extends Model
{
    /** @use HasFactory<\Database\Factories\IncomeFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'tenancy_id',
        'period_year',
        'period_month',
        'amount_ore',
        'received_on',
        'income_year',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'amount_ore' => MoneyCast::class,
            'received_on' => 'date',
            'income_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Income $income): void {
            if (empty($income->income_year) && ! empty($income->period_year)) {
                $income->income_year = $income->period_year;
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function isOutstanding(): bool
    {
        return $this->received_on === null;
    }
}
