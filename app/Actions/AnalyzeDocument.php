<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AnalysisStatus;
use App\Models\Document;
use App\Models\DocumentAnalysis;
use App\Services\DocumentAnalysis\DocumentAnalyzer;
use Throwable;

/**
 * Runs the AI analyzer over a bilag and persists the result as a reviewable
 * draft (brief §7): the raw model output and normalized suggestion are stored
 * together with the provider, model and prompt/schema versions for traceability.
 * A failure is recorded as a Failed analysis rather than thrown away, so the UI
 * can surface it and the bilag can still be entered manually.
 *
 * Pass an existing $into row (e.g. a Pending analysis created at drop time) to
 * fill it in place — that is how the background Innboks pipeline works. Omit it
 * to create a fresh Draft (the inline expense-form path).
 */
class AnalyzeDocument
{
    public function __construct(private readonly DocumentAnalyzer $analyzer)
    {
    }

    public function __invoke(Document $document, ?DocumentAnalysis $into = null): DocumentAnalysis
    {
        $stamp = [
            'document_id' => $document->id,
            'provider' => $this->analyzer->provider(),
            'model' => $this->analyzer->model(),
            'prompt_version' => $this->analyzer->promptVersion(),
            'schema_version' => $this->analyzer->schemaVersion(),
            'analyzed_at' => now(),
        ];

        try {
            $result = $this->analyzer->analyze($document);

            return $this->persist($into, [
                ...$stamp,
                'status' => AnalysisStatus::Draft->value,
                'raw_output' => $result->raw,
                'suggested' => $result->toArray(),
                'confidence' => $result->confidence,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->persist($into, [
                ...$stamp,
                'status' => AnalysisStatus::Failed->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(?DocumentAnalysis $into, array $attributes): DocumentAnalysis
    {
        if ($into) {
            $into->update($attributes);

            return $into;
        }

        return DocumentAnalysis::create($attributes);
    }
}
