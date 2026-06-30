<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Resolves a free-text property hint from a bilag (e.g. "Blekenberg 36",
 * "Damsgårdsveien 88") to one of the owner's properties — and, when the hint
 * carries a bruksenhet code (e.g. "H0201"), the specific unit in a bygård.
 *
 * Matching is deliberately forgiving (case/space-insensitive, bidirectional
 * substring on name + address, then a street-name + house-number fallback) but
 * never guesses across different streets. The result is a suggestion the user
 * still confirms in the expense form.
 */
class PropertyMatcher
{
    /** @return array{property: ?Property, unit: ?Unit} */
    public function match(?string $hint): array
    {
        $none = ['property' => null, 'unit' => null];

        $h = $this->normalize($hint);
        if ($h === '') {
            return $none;
        }

        $properties = Property::with('units')->get();

        $property = $this->matchByContains($properties, $h)
            ?? $this->matchByStreetAndNumber($properties, $h);

        if (! $property) {
            return $none;
        }

        return ['property' => $property, 'unit' => $this->matchUnit($property, $hint)];
    }

    /** Convenience: just the property id, or null. */
    public function matchPropertyId(?string $hint): ?int
    {
        return $this->match($hint)['property']?->id;
    }

    /** Name or address contained either way — the common, high-confidence case. */
    private function matchByContains(Collection $properties, string $h): ?Property
    {
        foreach ($properties as $property) {
            foreach ([$property->name, $property->address] as $candidate) {
                $c = $this->normalize($candidate);
                if ($c !== '' && (str_contains($h, $c) || str_contains($c, $h))) {
                    return $property;
                }
            }
        }

        return null;
    }

    /** Fallback: same street word AND same house number (e.g. "blekenberg" + "36"). */
    private function matchByStreetAndNumber(Collection $properties, string $h): ?Property
    {
        [$hStreet, $hNumber] = $this->streetAndNumber($h);
        if ($hStreet === null || $hNumber === null) {
            return null;
        }

        foreach ($properties as $property) {
            foreach ([$property->name, $property->address] as $candidate) {
                [$street, $number] = $this->streetAndNumber($this->normalize($candidate));
                if ($street !== null && $number === $hNumber && str_contains($street, $hStreet)) {
                    return $property;
                }
            }
        }

        return null;
    }

    /** Match a bruksenhet code (H0201 etc.) mentioned in the hint to a unit. */
    private function matchUnit(Property $property, ?string $hint): ?Unit
    {
        if (! preg_match('/\bH\d{4}\b/i', (string) $hint, $m)) {
            return null;
        }

        $code = strtoupper($m[0]);

        return $property->units->first(fn (Unit $u) => strtoupper((string) $u->code) === $code);
    }

    /** @return array{0: ?string, 1: ?string} street word, house number */
    private function streetAndNumber(string $value): array
    {
        if (! preg_match('/([a-zæøå]+).*?(\d+)/u', $value, $m)) {
            return [null, null];
        }

        return [$m[1], $m[2]];
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }
}
