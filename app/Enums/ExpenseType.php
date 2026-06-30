<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kostnadstype — the tax treatment of an expense. This is the most important
 * field in the system (brief §4): it is captured at entry, never inferred
 * later, and it determines whether an amount is deducted now, capitalised to
 * the property's cost basis, or depreciated.
 *
 * Case names are English; labels are the exact Norwegian tax terms.
 */
enum ExpenseType: string
{
    case Maintenance = 'maintenance';      // Vedlikehold — restoring prior standard
    case Operating = 'operating';          // Drift — running costs
    case Finance = 'finance';              // Finans — loan interest
    case Improvement = 'improvement';      // Påkostning — upgrade beyond prior standard
    case CapitalAsset = 'capital_asset';   // Driftsmiddel — depreciable movable asset

    /** Norwegian UI label (brief §11 glossary). */
    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Vedlikehold',
            self::Operating => 'Drift',
            self::Finance => 'Finans',
            self::Improvement => 'Påkostning',
            self::CapitalAsset => 'Driftsmiddel',
        };
    }

    /** Deductible in the income year the cost occurs. */
    public function isDeductibleNow(): bool
    {
        return in_array($this, [self::Maintenance, self::Operating, self::Finance], true);
    }

    /** Added to inngangsverdi (cost basis); not deductible now, reduces future gains tax. */
    public function isCapitalized(): bool
    {
        return $this === self::Improvement;
    }

    /** Depreciated via declining balance (saldoavskrivning). */
    public function isDepreciable(): bool
    {
        return $this === self::CapitalAsset;
    }

    /** Short Norwegian summary of the tax treatment, shown next to the field. */
    public function taxTreatment(): string
    {
        return match (true) {
            $this->isDeductibleNow() => 'Fradragsberettiget i året',
            $this->isCapitalized() => 'Aktiveres på inngangsverdi',
            default => 'Saldoavskrives over tid',
        };
    }

    /** @return array<int, array{value:string,label:string,treatment:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'treatment' => $t->taxTreatment(),
        ], self::cases());
    }
}
