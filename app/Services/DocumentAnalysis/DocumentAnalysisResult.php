<?php

declare(strict_types=1);

namespace App\Services\DocumentAnalysis;

use App\Enums\ExpenseType;
use App\Support\Money;

/**
 * Normalized result of analyzing a bilag. This is the suggestion a human
 * reviews and confirms (brief §3.4, §7) — never applied automatically. The
 * raw model output is preserved alongside for traceability.
 */
final readonly class DocumentAnalysisResult
{
    /**
     * @param  array<int, array{description?:string, amount_ore?:int}>  $lineItems
     * @param  array<int, array{date?:string, time?:?string, station?:string, amount_ore?:int}>  $tollPassings
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $documentType = 'kvittering',   // kvittering | faktura | bompenger | annet
        public ?string $vendor = null,
        public ?string $vendorOrgnr = null,
        public ?string $date = null,                    // ISO yyyy-mm-dd
        public ?string $dueDate = null,                 // ISO yyyy-mm-dd (invoices)
        public ?Money $total = null,
        public ?Money $vat = null,
        public string $currency = 'NOK',
        public ?string $invoiceNumber = null,
        public ?string $kid = null,
        public ?string $propertyHint = null,            // street address of the property the bilag concerns
        public array $lineItems = [],
        public array $tollPassings = [],                // individual bompassering rows (toll/ferry only)
        public ?string $suggestedCategory = null,
        public ?ExpenseType $suggestedType = null,
        public ?string $rationale = null,               // short Norwegian explanation
        public float $confidence = 0.0,                 // 0.0–1.0
        public array $raw = [],
    ) {
    }

    /**
     * The normalized suggestion, stored as the `suggested` JSON on the
     * document_analyses row.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_type' => $this->documentType,
            'vendor' => $this->vendor,
            'vendor_orgnr' => $this->vendorOrgnr,
            'date' => $this->date,
            'due_date' => $this->dueDate,
            'total_ore' => $this->total?->ore,
            'vat_ore' => $this->vat?->ore,
            'currency' => $this->currency,
            'invoice_number' => $this->invoiceNumber,
            'kid' => $this->kid,
            'property_hint' => $this->propertyHint,
            'line_items' => $this->lineItems,
            'toll_passings' => $this->tollPassings,
            'suggested_category' => $this->suggestedCategory,
            'suggested_type' => $this->suggestedType?->value,
            'rationale' => $this->rationale,
            'confidence' => $this->confidence,
        ];
    }
}
