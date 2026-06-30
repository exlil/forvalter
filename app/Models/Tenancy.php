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
 * Leieforhold — a tenancy over a period. The period drives forholdsberegning.
 */
class Tenancy extends Model
{
    /** @use HasFactory<\Database\Factories\TenancyFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'tenancies';

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'starts_on',
        'ends_on',
        'monthly_rent_ore',
        'deposit_ore',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'monthly_rent_ore' => MoneyCast::class,
            'deposit_ore' => MoneyCast::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function isOngoing(): bool
    {
        return $this->ends_on === null || ! $this->ends_on->isPast();
    }
}
