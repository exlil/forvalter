<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyzeDocumentJob;
use App\Models\Document;
use App\Models\DocumentAnalysis;
use App\Services\DocumentAnalysis\DocumentAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Receives bilag dropped anywhere in the app (a plain multipart POST from the
 * global drop layer). Each file is stored as an immutable Document with a
 * Pending DocumentAnalysis, then AI extraction is kicked off after the response
 * is sent. The Innboks streams in the results — nothing is applied automatically.
 */
class IngestDocumentController extends Controller
{
    public function __invoke(Request $request, DocumentAnalyzer $analyzer): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'files.required' => 'Ingen filer mottatt.',
            'files.*.mimes' => 'Bilaget må være PDF, JPG eller PNG.',
            'files.*.max' => 'Bilaget kan være maks 10 MB.',
        ]);

        $ids = [];

        foreach ($request->file('files') as $file) {
            $hash = hash_file('sha256', $file->getRealPath());
            $path = $file->store('documents', 'local');

            $document = Document::create([
                'type' => 'receipt',
                'original_filename' => $file->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'hash' => $hash,
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);

            // A Pending row so the bilag appears in the Innboks at once; the
            // job below fills in the extraction. Stamped now so the column
            // NOT-NULLs are satisfied even before analysis runs.
            $analysis = DocumentAnalysis::create([
                'document_id' => $document->id,
                'provider' => $analyzer->provider(),
                'model' => $analyzer->model(),
                'prompt_version' => $analyzer->promptVersion(),
                'schema_version' => $analyzer->schemaVersion(),
                'status' => AnalysisStatus::Pending->value,
            ]);

            AnalyzeDocumentJob::dispatch($analysis->id)->afterResponse();

            $ids[] = $analysis->id;
        }

        return response()->json([
            'count' => count($ids),
            'ids' => $ids,
        ]);
    }
}
