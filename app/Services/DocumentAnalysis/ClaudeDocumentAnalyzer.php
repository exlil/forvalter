<?php

declare(strict_types=1);

namespace App\Services\DocumentAnalysis;

use Anthropic\Client;
use App\Enums\ExpenseType;
use App\Models\Document;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Claude-backed bilag extraction via the official Anthropic PHP SDK. Handles
 * Norwegian text, NOK formatting, multi-page PDFs and photos; returns partial
 * results with a low confidence rather than guessing (brief §7). The model and
 * prompt are versioned and swappable behind {@see DocumentAnalyzer}.
 */
class ClaudeDocumentAnalyzer implements DocumentAnalyzer
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly int $maxTokens,
    ) {
    }

    public function analyze(Document $document): DocumentAnalysisResult
    {
        $bytes = Storage::disk($document->disk)->get($document->path);
        if ($bytes === null) {
            throw new RuntimeException("Fant ikke bilagsfilen: {$document->path}");
        }

        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: $this->maxTokens,
            system: ReceiptExtractionPrompt::system(),
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => ReceiptExtractionPrompt::instruction()],
                    $this->sourceBlock($document, base64_encode($bytes)),
                ],
            ]],
        );

        return $this->toResult($this->decodeJson($this->extractText($message)));
    }

    /** Build the PDF document block or image block depending on the bilag's MIME type. */
    private function sourceBlock(Document $document, string $base64): array
    {
        $mime = $document->mime_type ?? 'application/octet-stream';

        if ($mime === 'application/pdf') {
            return [
                'type' => 'document',
                'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64],
            ];
        }

        return [
            'type' => 'image',
            'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64],
        ];
    }

    private function extractText(object $message): string
    {
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                return (string) $block->text;
            }
        }

        throw new RuntimeException('Tomt svar fra modellen.');
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('Klarte ikke å tolke JSON fra modellsvaret.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Ugyldig JSON fra modellsvaret.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $data */
    private function toResult(array $data): DocumentAnalysisResult
    {
        $money = fn (string $key) => $this->kronerToMoney($data[$key] ?? null);

        return new DocumentAnalysisResult(
            documentType: (string) ($data['document_type'] ?? 'annet'),
            vendor: $data['vendor'] ?? null,
            vendorOrgnr: $data['vendor_orgnr'] ?? null,
            date: $data['date'] ?? null,
            dueDate: $data['due_date'] ?? null,
            total: $money('total_kroner'),
            vat: $money('vat_kroner'),
            currency: (string) ($data['currency'] ?? 'NOK'),
            invoiceNumber: $data['invoice_number'] ?? null,
            kid: $data['kid'] ?? null,
            propertyHint: $data['property_hint'] ?? null,
            lineItems: $this->lineItems($data['line_items'] ?? []),
            tollPassings: $this->tollPassings($data['toll_passings'] ?? []),
            suggestedCategory: $data['suggested_category'] ?? null,
            suggestedType: ExpenseType::tryFrom((string) ($data['suggested_type'] ?? '')),
            rationale: $data['rationale'] ?? null,
            confidence: (float) ($data['confidence'] ?? 0.0),
            raw: $data,
        );
    }

    /** @param array<int, mixed> $items @return array<int, array{description:string, amount_ore:int}> */
    private function lineItems(array $items): array
    {
        return collect($items)
            ->filter(fn ($i) => is_array($i))
            ->map(fn (array $i) => [
                'description' => (string) ($i['description'] ?? ''),
                'amount_ore' => $this->kronerToMoney($i['amount_kroner'] ?? null)?->ore ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * Individual bompasseringer from a toll/ferry statement. Each is kept whole
     * (incl. 0,00 passings) so it can later be matched to the kjørebok; which
     * passings are deductible is decided there, not here.
     *
     * @param array<int, mixed> $items
     * @return array<int, array{date:?string, time:?string, station:string, amount_ore:int}>
     */
    private function tollPassings(array $items): array
    {
        return collect($items)
            ->filter(fn ($i) => is_array($i))
            ->map(fn (array $i) => [
                'date' => $i['date'] ?? null,
                'time' => $i['time'] ?? null,
                'station' => (string) ($i['station'] ?? ''),
                'amount_ore' => $this->kronerToMoney($i['amount_kroner'] ?? null)?->ore ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * The model returns amounts as JSON numbers in kroner (e.g. 249.8), where
     * "." is the decimal point. Convert numerics directly; only fall back to
     * the Norwegian-string parser for the rare case the model returns a string.
     */
    private function kronerToMoney(mixed $value): ?Money
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return Money::fromOre((int) round(((float) $value) * 100));
        }

        return Money::fromKronerString((string) $value);
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function promptVersion(): string
    {
        return ReceiptExtractionPrompt::PROMPT_VERSION;
    }

    public function schemaVersion(): string
    {
        return ReceiptExtractionPrompt::SCHEMA_VERSION;
    }
}
