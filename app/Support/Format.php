<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Norwegian display formatting helpers. Storage stays ISO/native; only output
 * is localised — dd.mm.yyyy dates, space-grouped thousands, comma decimals.
 */
final class Format
{
    public const PLACEHOLDER = '—';

    /** Numeric date: 28.06.2026 */
    public static function date(?CarbonInterface $date): string
    {
        return $date?->format('d.m.Y') ?? self::PLACEHOLDER;
    }

    /** Editorial date in bokmål: "28. jun. 2026" */
    public static function dateLong(?CarbonInterface $date): string
    {
        return $date ? $date->locale('nb')->isoFormat('D. MMM YYYY') : self::PLACEHOLDER;
    }

    /** Month + year in bokmål: "juni 2026" */
    public static function monthYear(?CarbonInterface $date): string
    {
        return $date ? $date->locale('nb')->isoFormat('MMMM YYYY') : self::PLACEHOLDER;
    }

    /** Plain number with Norwegian grouping: 1 840 / 8,2 */
    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
