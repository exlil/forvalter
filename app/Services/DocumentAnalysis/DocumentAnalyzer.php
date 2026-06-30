<?php

declare(strict_types=1);

namespace App\Services\DocumentAnalysis;

use App\Models\Document;

/**
 * Contract for extracting a reviewable draft from a bilag. Implementations are
 * swappable and self-describing (provider, model, prompt/schema versions) so
 * analyses stay traceable and the model can be improved over time without
 * touching the rest of the system (brief §7).
 */
interface DocumentAnalyzer
{
    public function analyze(Document $document): DocumentAnalysisResult;

    public function provider(): string;

    public function model(): string;

    public function promptVersion(): string;

    public function schemaVersion(): string;
}
