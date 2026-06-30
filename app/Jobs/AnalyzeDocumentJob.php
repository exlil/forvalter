<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\AnalyzeDocument;
use App\Enums\AnalysisStatus;
use App\Models\DocumentAnalysis;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Background AI extraction for a dropped bilag. A Pending DocumentAnalysis is
 * created the moment a file lands (so it shows in the Innboks immediately); this
 * job fills it in afterwards. Dispatched with ->afterResponse() so it runs in
 * the same process once the upload response is sent — no queue worker required,
 * while the Innboks polls for the result. Idempotent: only acts on Pending rows.
 */
class AnalyzeDocumentJob
{
    use Dispatchable;

    public function __construct(public int $analysisId)
    {
    }

    public function handle(AnalyzeDocument $analyze): void
    {
        $analysis = DocumentAnalysis::with('document')->find($this->analysisId);

        if (! $analysis || ! $analysis->document) {
            return;
        }

        if ($analysis->status !== AnalysisStatus::Pending) {
            return;
        }

        $analyze($analysis->document, $analysis);
    }
}
