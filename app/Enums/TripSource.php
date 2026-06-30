<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a mileage trip was captured. The phone companion (brief §8) submits
 * via the authenticated capture API and is recorded as Api.
 */
enum TripSource: string
{
    case Web = 'web';     // Entered in the web app
    case Api = 'api';     // Submitted from the phone companion

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Api => 'Mobil',
        };
    }
}
