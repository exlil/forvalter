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
 * Lån — a loan per property. Interest (deductible) and principal (not) are kept
 * separate; interest is recorded as finance expenses (brief §5).
 */
class Loan extends Model
{
    /** @use HasFactory<\Database\Factories\LoanFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'property_id',
        'lender',
        'original_principal_ore',
        'current_balance_ore',
        'interest_rate',
        'started_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_principal_ore' => MoneyCast::class,
            'current_balance_ore' => MoneyCast::class,
            'interest_rate' => 'decimal:3',
            'started_on' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
