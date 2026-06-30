<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Exact monetary value, stored as an integer number of øre (1 NOK = 100 øre).
 *
 * Money is never represented as a float internally — all arithmetic happens on
 * integer øre, so tax sums always reconcile to the krone (brief §3.1). Floats
 * are only ever produced for display convenience via {@see kroner()}.
 */
final readonly class Money implements Stringable
{
    public function __construct(public int $ore)
    {
    }

    public static function fromOre(int $ore): self
    {
        return new self($ore);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromKroner(int $kroner): self
    {
        return new self($kroner * 100);
    }

    /**
     * Parse a human-entered amount in kroner into exact øre, tolerating
     * Norwegian formatting: space/period thousands separators and a comma
     * (or period) decimal separator. Examples that parse correctly:
     *   "13 500"  → 1 350 000 øre
     *   "3,50"    →       350 øre
     *   "1.234,56"→   123 456 øre
     *   "kr 1 200"→   120 000 øre
     */
    public static function fromKronerString(string $input): self
    {
        $s = trim($input);
        if ($s === '') {
            return new self(0);
        }

        $negative = str_contains($s, '-') || str_contains($s, '−');
        $s = preg_replace('/[^0-9.,]/', '', $s) ?? '';

        if ($s === '') {
            return new self(0);
        }

        $hasComma = str_contains($s, ',');
        $hasDot = str_contains($s, '.');

        if ($hasComma && $hasDot) {
            // The right-most separator is the decimal separator.
            $decimalSep = strrpos($s, ',') > strrpos($s, '.') ? ',' : '.';
            $thousandSep = $decimalSep === ',' ? '.' : ',';
            $s = str_replace($thousandSep, '', $s);
            $s = str_replace($decimalSep, '.', $s);
        } elseif ($hasComma) {
            // Norwegian decimal comma.
            $s = str_replace(',', '.', $s);
        } elseif ($hasDot) {
            // A lone period with exactly two trailing digits is a decimal;
            // otherwise it is a thousands separator.
            $parts = explode('.', $s);
            $isDecimal = count($parts) === 2 && strlen($parts[1]) === 2;
            if (! $isDecimal) {
                $s = str_replace('.', '', $s);
            }
        }

        [$whole, $frac] = array_pad(explode('.', $s, 2), 2, '0');
        $frac = substr(str_pad($frac, 2, '0'), 0, 2);
        $ore = ((int) $whole) * 100 + (int) $frac;

        return new self($negative ? -$ore : $ore);
    }

    public function add(self $other): self
    {
        return new self($this->ore + $other->ore);
    }

    public function subtract(self $other): self
    {
        return new self($this->ore - $other->ore);
    }

    public function negate(): self
    {
        return new self(-$this->ore);
    }

    public function abs(): self
    {
        return new self(abs($this->ore));
    }

    public function multiply(int|float $factor): self
    {
        return new self((int) round($this->ore * $factor));
    }

    /**
     * Split into $parts as evenly as possible, distributing any remainder øre
     * one-by-one across the first parts so the pieces always sum back to the
     * original. Used for the 50/50 owner split and pro-rata apportionment
     * (forholdsberegning) without ever losing or inventing a øre.
     *
     * @return array<int, self>
     */
    public function split(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Cannot split money into fewer than one part.');
        }

        $base = intdiv($this->ore, $parts);
        $remainder = $this->ore - ($base * $parts);

        $result = [];
        for ($i = 0; $i < $parts; $i++) {
            $result[] = new self($base + ($i < abs($remainder) ? ($remainder <=> 0) : 0));
        }

        return $result;
    }

    /**
     * Allocate across the given integer ratios, distributing remainder øre to
     * the largest-ratio parts first. The allocated pieces always sum to the
     * original amount (Fowler's money allocation).
     *
     * @param  array<int|string, int>  $ratios
     * @return array<int|string, self>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);
        if ($total <= 0) {
            throw new InvalidArgumentException('Allocation ratios must sum to a positive value.');
        }

        $result = [];
        $remainder = $this->ore;
        foreach ($ratios as $key => $ratio) {
            $share = intdiv($this->ore * $ratio, $total);
            $result[$key] = $share;
            $remainder -= $share;
        }

        // Hand out the leftover øre, largest ratio first.
        arsort($ratios);
        foreach (array_keys($ratios) as $key) {
            if ($remainder === 0) {
                break;
            }
            $step = $remainder <=> 0;
            $result[$key] += $step;
            $remainder -= $step;
        }

        return array_map(static fn (int $ore): self => new self($ore), $result);
    }

    public function isZero(): bool
    {
        return $this->ore === 0;
    }

    public function isNegative(): bool
    {
        return $this->ore < 0;
    }

    public function isPositive(): bool
    {
        return $this->ore > 0;
    }

    public function equals(self $other): bool
    {
        return $this->ore === $other->ore;
    }

    public function greaterThan(self $other): bool
    {
        return $this->ore > $other->ore;
    }

    public function lessThan(self $other): bool
    {
        return $this->ore < $other->ore;
    }

    /**
     * Lossy float in kroner — for display and charting only, never for storage
     * or arithmetic that must reconcile.
     */
    public function kroner(): float
    {
        return $this->ore / 100;
    }

    /**
     * Norwegian-formatted kroner: space thousands separator, comma decimals.
     *   format()                → "kr 13 500"
     *   format(decimals: true)  → "kr 13 500,50"
     *   format(symbol: false)   → "13 500"
     * Negatives use a true minus sign (−), e.g. "−kr 3 180".
     */
    public function format(bool $symbol = true, bool $decimals = false): string
    {
        $abs = abs($this->ore);
        $whole = intdiv($abs, 100);
        $frac = $abs % 100;

        $out = number_format($whole, 0, ',', ' ');
        if ($decimals) {
            $out .= ','.str_pad((string) $frac, 2, '0', STR_PAD_LEFT);
        }
        if ($symbol) {
            $out = 'kr '.$out;
        }

        return ($this->ore < 0 ? '−' : '').$out;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
