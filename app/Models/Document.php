<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Bilag — the uploaded, immutable source document an expense references.
 */
class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'hash',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(DocumentAnalysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(DocumentAnalysis::class)->latestOfMany();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Temporary URL / path for viewing the stored bilag. */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
