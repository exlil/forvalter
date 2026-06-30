<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Leietaker — a tenant.
 */
class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'notes',
    ];

    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }
}
