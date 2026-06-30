<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts an integer øre column to a {@see Money} value object and back.
 *
 * When assigning, pass a Money instance (preferred) or an int already in øre.
 * A string is parsed as Norwegian-formatted kroner via Money::fromKronerString().
 *
 * @implements CastsAttributes<Money, Money|int|string>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : new Money((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->ore;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Money::fromKronerString($value)->ore;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot cast value of type %s to Money for [%s].', get_debug_type($value), $key)
        );
    }
}
