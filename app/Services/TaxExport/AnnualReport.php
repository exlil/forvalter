<?php

declare(strict_types=1);

namespace App\Services\TaxExport;

use App\Enums\ExpenseType;
use App\Models\AssetDepreciation;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Property;
use App\Models\Trip;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Builds the year-end skattemelding summary (brief §6, north-star). Pure read
 * model — it never writes. Every figure derives from the atomic records, grouped
 * by the tax-critical kostnadstype:
 *
 *   - vedlikehold / drift / finans  → deductible in the income year
 *   - kjøring (kjørebok)            → deductible (per-km × snapshot rate)
 *   - avskrivning (driftsmidler)    → deductible (the year's saldoavskrivning)
 *   - påkostning                    → NOT deducted; capitalised to inngangsverdi
 *   - driftsmiddel-kjøp             → NOT deducted directly; deduction is the avskrivning
 *
 * Net = leieinntekt − (fradragsberettigede kostnader). The portfolio is owned
 * 50/50, so the result is also presented per owner.
 */
class AnnualReport
{
    public function __construct(private readonly int $year)
    {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $properties = Property::with('units')->orderBy('name')->get();

        $rows = $properties->map(fn (Property $p) => $this->propertyRow($p))->all();

        // Mileage not tied to a property (felles kjøring) is still deductible.
        $fellesTrips = Trip::whereNull('property_id')->where('income_year', $this->year)->get();
        $felles = [
            'km' => (int) $fellesTrips->sum('distance_km'),
            'deduction' => new Money((int) $fellesTrips->sum(fn (Trip $t) => $t->deduction_ore->ore)),
            'trips' => $fellesTrips->count(),
        ];

        $income = array_sum(array_map(fn ($r) => $r['income']->ore, $rows));
        $deductible = array_sum(array_map(fn ($r) => $r['deductible_total']->ore, $rows)) + $felles['deduction']->ore;
        $net = $income - $deductible;
        $improvement = array_sum(array_map(fn ($r) => $r['improvement']->ore, $rows));
        $depreciation = array_sum(array_map(fn ($r) => $r['depreciation']->ore, $rows));

        return [
            'year' => $this->year,
            'properties' => $rows,
            'felles_mileage' => $felles,
            'totals' => [
                'income' => new Money($income),
                'deductible' => new Money($deductible),
                'net' => new Money($net),
                'improvement' => new Money((int) $improvement),
                'depreciation' => new Money((int) $depreciation),
                'per_owner' => new Money((int) round($net / 2)),
            ],
            'documentation' => $this->documentation(),
        ];
    }

    /** @return array<string, mixed> */
    private function propertyRow(Property $property): array
    {
        $unitIds = $property->units->pluck('id');

        $incomeQuery = Income::whereIn('unit_id', $unitIds)->where('income_year', $this->year);
        $income = (int) (clone $incomeQuery)->sum('amount_ore');
        $outstanding = (int) (clone $incomeQuery)->whereNull('received_on')->sum('amount_ore');

        $expenses = Expense::where('property_id', $property->id)->where('income_year', $this->year)->get();

        $sumType = fn (ExpenseType $t) => (int) $expenses->where('type', $t)->sum(fn (Expense $e) => $e->amount_ore->ore);

        $maintenance = $sumType(ExpenseType::Maintenance);
        $operating = $sumType(ExpenseType::Operating);
        $finance = $sumType(ExpenseType::Finance);
        $improvement = $sumType(ExpenseType::Improvement);
        $capitalAsset = $sumType(ExpenseType::CapitalAsset);

        $trips = Trip::where('property_id', $property->id)->where('income_year', $this->year)->get();
        $mileageDeduction = (int) $trips->sum(fn (Trip $t) => $t->deduction_ore->ore);

        $depreciation = (int) AssetDepreciation::where('income_year', $this->year)
            ->whereHas('asset', fn ($q) => $q->where('property_id', $property->id))
            ->sum('depreciation_ore');

        $deductibleTotal = $maintenance + $operating + $finance + $mileageDeduction + $depreciation;

        // Category breakdown of the deductible expenses (descriptive axis).
        $categories = $expenses
            ->whereIn('type', [ExpenseType::Maintenance, ExpenseType::Operating, ExpenseType::Finance])
            ->groupBy(fn (Expense $e) => $e->category ?: 'Uten kategori')
            ->map(fn (Collection $g, $label) => [
                'label' => $label,
                'amount' => new Money((int) $g->sum(fn (Expense $e) => $e->amount_ore->ore)),
            ])
            ->sortByDesc(fn ($c) => $c['amount']->ore)
            ->values()
            ->all();

        return [
            'id' => $property->id,
            'name' => $property->name,
            'is_building' => $property->isBuilding(),
            'income' => new Money($income),
            'income_outstanding' => new Money($outstanding),
            'costs' => [
                'maintenance' => new Money($maintenance),
                'operating' => new Money($operating),
                'finance' => new Money($finance),
            ],
            'categories' => $categories,
            'mileage' => [
                'km' => (int) $trips->sum('distance_km'),
                'deduction' => new Money($mileageDeduction),
                'trips' => $trips->count(),
            ],
            'depreciation' => new Money($depreciation),
            'improvement' => new Money($improvement),
            'capital_asset_purchase' => new Money($capitalAsset),
            'deductible_total' => new Money($deductibleTotal),
            'net' => new Money($income - $deductibleTotal),
            'cost_basis' => $property->costBasis(),
            'expense_count' => $expenses->count(),
            'expense_with_doc' => $expenses->whereNotNull('document_id')->count(),
        ];
    }

    /** Documentation completeness — which expenses lack an attached bilag. */
    private function documentation(): array
    {
        $expenses = Expense::with('property')->where('income_year', $this->year)->get();
        $missing = $expenses->whereNull('document_id');

        return [
            'total_expenses' => $expenses->count(),
            'with_doc' => $expenses->whereNotNull('document_id')->count(),
            'missing' => $missing->count(),
            'missing_list' => $missing->map(fn (Expense $e) => [
                'property' => $e->property?->name ?? '—',
                'date' => $e->date?->format('d.m.Y'),
                'vendor' => $e->vendor ?: ($e->description ?: $e->category ?: 'Utgift'),
                'amount' => $e->amount_ore,
                'type' => $e->type->label(),
            ])->values()->all(),
        ];
    }
}
