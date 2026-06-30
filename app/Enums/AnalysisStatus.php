<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an AI document analysis. The machine output is always a
 * suggestion a human confirms or discards — never auto-applied (brief §3.4, §7).
 */
enum AnalysisStatus: string
{
    case Pending = 'pending';       // Behandles — queued / running
    case Draft = 'draft';           // Utkast — awaiting human review
    case Confirmed = 'confirmed';   // Bekreftet — values accepted into an expense
    case Discarded = 'discarded';   // Forkastet — rejected by the reviewer
    case Failed = 'failed';         // Feilet — analysis could not be produced

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Behandles',
            self::Draft => 'Utkast',
            self::Confirmed => 'Bekreftet',
            self::Discarded => 'Forkastet',
            self::Failed => 'Feilet',
        };
    }
}
