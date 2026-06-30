<?php

declare(strict_types=1);

namespace App\Services\DocumentAnalysis;

use App\Enums\ExpenseType;
use App\Models\Document;
use App\Support\Money;

/**
 * Deterministic stub analyzer. Returns a plausible Norwegian extraction so the
 * intake flow works end-to-end without an API key (dev, tests, and the
 * "no key configured" fallback). Never makes a network call.
 */
class FakeDocumentAnalyzer implements DocumentAnalyzer
{
    public function analyze(Document $document): DocumentAnalysisResult
    {
        $total = Money::fromKroner(1249);
        $vat = Money::fromOre((int) round($total->ore * 0.20)); // 25 % MVA basis ≈ 20 % of gross

        return new DocumentAnalysisResult(
            documentType: 'kvittering',
            vendor: 'Maxbo Bygg AS',
            vendorOrgnr: '912345678',
            date: now()->format('Y-m-d'),
            total: $total,
            vat: $vat,
            currency: 'NOK',
            invoiceNumber: null,
            lineItems: [
                ['description' => 'Blandebatteri kjøkken', 'amount_ore' => 99900],
                ['description' => 'Pakninger og rørdeler', 'amount_ore' => 25000],
            ],
            suggestedCategory: 'Vedlikehold',
            suggestedType: ExpenseType::Maintenance,
            rationale: 'Utskifting av eksisterende blandebatteri gjenoppretter tidligere standard – vedlikehold, fradragsberettiget i året.',
            confidence: 0.92,
            raw: ['stub' => true, 'source' => $document->original_filename],
        );
    }

    public function provider(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'stub';
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
