<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved mileage route used to pre-fill the trip form (brief §8: fast logging).
 */
class TripFavorite extends Model
{
    /** @use HasFactory<\Database\Factories\TripFavoriteFactory> */
    use HasFactory;

    protected $fillable = [
        'label',
        'property_id',
        'distance_km',
        'purpose',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
