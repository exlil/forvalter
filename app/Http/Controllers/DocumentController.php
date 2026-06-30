<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a stored bilag for viewing. Behind auth (the whole app is) and streamed
 * from the private disk — bilag are never publicly reachable by URL. Inline so
 * PDFs and images open in the browser; ?last=1 forces a download.
 */
class DocumentController extends Controller
{
    public function show(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        $disposition = request()->boolean('last') ? 'attachment' : 'inline';

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
            $disposition,
        );
    }
}
