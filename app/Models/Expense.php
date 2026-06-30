<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ExpenseType;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kostnad — an expense. `type` (kostnadstype) is the required tax axis and
 * decides deductibility; `category` is descriptive. Amounts are integer øre.
 */
class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'property_id',
        'unit_id',
        'document_id',
        'document_analysis_id',
        'date',
        'amount_ore',
        'vat_ore',
        'vendor',
        'vendor_orgnr',
        'category',
        'type',
        'description',
        'income_year',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount_ore' => MoneyCast::class,
            'vat_ore' => MoneyCast::class,
            'type' => ExpenseType::class,
            'income_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // income_year derives from the expense date unless set explicitly.
        static::saving(function (Expense $expense): void {
            if (empty($expense->income_year) && $expense->date !== null) {
                $expense->income_year = $expense->date->year;
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(DocumentAnalysis::class, 'document_analysis_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDeductibleNow(): bool
    {
        return $this->type->isDeductibleNow();
    }

    public function hasReceipt(): bool
    {
        return $this->document_id !== null;
    }
}
