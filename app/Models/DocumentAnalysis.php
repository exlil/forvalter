<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI-analyse — a versioned extraction of a bilag. Stores the raw machine output
 * and the normalized suggestions together; a human confirms before any value is
 * applied to an expense (brief §3.4, §7).
 */
class DocumentAnalysis extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentAnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'document_id',
        'provider',
        'model',
        'prompt_version',
        'schema_version',
        'status',
        'raw_output',
        'suggested',
        'confidence',
        'confirmed_expense_id',
        'confirmed_at',
        'analyzed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'raw_output' => 'array',
            'suggested' => 'array',
            'confidence' => 'float',
            'confirmed_at' => 'datetime',
            'analyzed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function confirmedExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'confirmed_expense_id');
    }
}
