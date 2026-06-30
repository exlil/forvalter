<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of uploaded bilag. A document is the canonical, immutable source of
 * truth that an expense references (brief §3.2).
 */
enum DocumentType: string
{
    case Receipt = 'receipt';     // Kvittering
    case Invoice = 'invoice';     // Faktura
    case Contract = 'contract';   // Kontrakt
    case Other = 'other';         // Annet

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Kvittering',
            self::Invoice => 'Faktura',
            self::Contract => 'Kontrakt',
            self::Other => 'Annet',
        };
    }
}
