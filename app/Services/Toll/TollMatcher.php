<?php

declare(strict_types=1);

namespace App\Services\Toll;

use App\Models\Trip;

/**
 * Matches the individual passings on a bompenger/ferje statement to the kjørebok.
 *
 * A toll statement covers all of a month's driving — private and rental. Only a
 * passing on a date with a registered business trip is a deductible driftskostnad,
 * and it inherits that trip's property. Matching is by DATE (the kjørebok records
 * no clock time); the human reviews and toggles each passing before booking.
 */
class TollMatcher
{
    /**
     * @param  array<int, array{date?:?string, time?:?string, station?:string, amount_ore?:int}>  $passings
     * @return array<int, array{index:int, date:?string, time:?string, station:string, amount_ore:int, matched:bool, trip_id:?int, trip_purpose:?string, property_id:?int, property_name:?string, trip_count:int}>
     */
    public function match(array $passings): array
    {
        $dates = collect($passings)->pluck('date')->filter()->unique()->values()->all();

        $tripsByDate = empty($dates)
            ? collect()
            : Trip::with('property')
                ->whereIn('date', $dates)
                ->get()
                ->groupBy(fn (Trip $t) => $t->date->format('Y-m-d'));

        return collect($passings)->map(function (array $p, int $i) use ($tripsByDate) {
            $date = $p['date'] ?? null;
            $trips = $date ? ($tripsByDate->get($date) ?? collect()) : collect();
            // Prefer a trip that carries a property so the passing can be attributed.
            $trip = $trips->first(fn (Trip $t) => $t->property_id !== null) ?? $trips->first();

            return [
                'index' => $i,
                'date' => $date,
                'time' => $p['time'] ?? null,
                'station' => (string) ($p['station'] ?? ''),
                'amount_ore' => (int) ($p['amount_ore'] ?? 0),
                'matched' => $trips->isNotEmpty(),
                'trip_id' => $trip?->id,
                'trip_purpose' => $trip?->purpose,
                'property_id' => $trip?->property_id,
                'property_name' => $trip?->property?->name,
                'trip_count' => $trips->count(),
            ];
        })->all();
    }

    /**
     * Sum (in øre) of the passings that match a trip — the default deductible total.
     *
     * @param  array<int, array{date?:?string, amount_ore?:int}>  $passings
     */
    public function matchedTotalOre(array $passings): int
    {
        return collect($this->match($passings))
            ->where('matched', true)
            ->sum('amount_ore');
    }
}
