<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Occupancy status of a unit. Derived at runtime from tenancies and rent
 * balances — stored nowhere as primary truth (brief §3.7).
 */
enum UnitStatus: string
{
    case Rented = 'rented';     // Utleid
    case Arrears = 'arrears';   // Restanse — let, but rent overdue
    case Vacant = 'vacant';     // Ledig

    public function label(): string
    {
        return match ($this) {
            self::Rented => 'Utleid',
            self::Arrears => 'Restanse',
            self::Vacant => 'Ledig',
        };
    }

    /** Semantic token consumed by the status-pill Blade component. */
    public function tone(): string
    {
        return match ($this) {
            self::Rented => 'positive',
            self::Arrears => 'accent',
            self::Vacant => 'muted',
        };
    }
}
