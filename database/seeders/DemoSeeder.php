<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExpenseType;
use App\Enums\TripSource;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Loan;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo portfolio using the owner's real Bergen properties: one bygård
 * (Svaneviksveien 85, 4 units) plus two frittstående apartments (Blekenberg 36,
 * Damsgårdsveien 88). Tenant names, rents and unit codes are illustrative.
 *
 * Felleskostnader are held at building level (property-level expenses with no
 * unit); each unit carries only its own direct costs.
 */
class DemoSeeder extends Seeder
{
    private ?int $userId = null;

    public function run(): void
    {
        $this->userId = User::query()->orderBy('id')->value('id');

        $this->seedBuilding();
        $this->seedStandalone();
        $this->seedTrips();
    }

    private function seedBuilding(): void
    {
        $property = Property::create([
            'name' => 'Svaneviksveien 85',
            'address' => 'Svaneviksveien 85',
            'postal_code' => '5161',
            'city' => 'Bergen',
            'property_type' => 'Bygård',
            'purchase_date' => '2016-09-01',
            'purchase_price_ore' => 18_500_000 * 100,
        ]);

        Loan::create([
            'property_id' => $property->id,
            'lender' => 'DNB',
            'original_principal_ore' => 12_000_000 * 100,
            'current_balance_ore' => 9_400_000 * 100,
            'interest_rate' => 5.250,
            'started_on' => '2016-09-01',
        ]);

        $units = [
            ['name' => 'Leil. 1', 'code' => 'H0101', 'type' => '2-roms', 'area' => 48, 'rooms' => 2, 'tenant' => 'Lene Haugen',   'since' => '2023-03-01', 'rent' => 13_500, 'status' => 'utleid',
                'expense' => ['2026-06-24', 'Vedlikehold', ExpenseType::Maintenance, 1_900, 'Kjøkken — utbedring']],
            ['name' => 'Leil. 2', 'code' => 'H0201', 'type' => '3-roms', 'area' => 67, 'rooms' => 3, 'tenant' => 'Familien Berg', 'since' => '2021-08-01', 'rent' => 15_200, 'status' => 'utleid',
                'expense' => ['2026-06-18', 'Vedlikehold', ExpenseType::Maintenance, 2_200, 'Vindu — tetting']],
            ['name' => 'Leil. 3', 'code' => 'H0301', 'type' => '2-roms', 'area' => 52, 'rooms' => 2, 'tenant' => 'Mats Olsen',    'since' => '2024-01-01', 'rent' => 11_800, 'status' => 'utleid',
                'expense' => ['2026-06-09', 'Vedlikehold', ExpenseType::Maintenance, 1_300, 'Dør — justering']],
            ['name' => 'Leil. 4', 'code' => 'H0401', 'type' => '3-roms', 'area' => 71, 'rooms' => 3, 'tenant' => 'Per Nilsen',    'since' => '2025-02-01', 'rent' => 14_000, 'status' => 'restanse',
                'expense' => ['2026-05-04', 'Vedlikehold', ExpenseType::Maintenance, 1_500, 'Vedlikehold']],
        ];

        foreach ($units as $u) {
            $this->makeUnit($property, $u);
        }

        // Felleskostnader (June 2026) — property-level, no unit. Sum = kr 20 800/mnd.
        $felles = [
            ['Renter', ExpenseType::Finance, 8_600, 'Renter (lån på bygg)'],
            ['Kommunale avgifter', ExpenseType::Operating, 4_300, 'Kommunale avgifter'],
            ['Vedlikehold', ExpenseType::Maintenance, 3_200, 'Vedlikehold bygg'],
            ['Forsikring', ExpenseType::Operating, 2_900, 'Forsikring bygg'],
            ['Strøm', ExpenseType::Operating, 1_800, 'Felles strøm / oppvarming'],
        ];
        foreach ($felles as [$category, $type, $kr, $desc]) {
            Expense::create([
                'property_id' => $property->id,
                'unit_id' => null,
                'date' => '2026-06-01',
                'amount_ore' => $kr * 100,
                'category' => $category,
                'type' => $type->value,
                'description' => $desc,
                'income_year' => 2026,
                'created_by' => $this->userId,
            ]);
        }
    }

    private function seedStandalone(): void
    {
        $apartments = [
            [
                'name' => 'Blekenberg 36', 'postal' => '5055', 'type' => '2-roms', 'area' => 49, 'rooms' => 2,
                'tenant' => 'Ingrid Dahl', 'since' => '2024-01-01', 'rent' => 11_800, 'status' => 'utleid',
                'purchase' => ['2019-03-01', 2_800_000],
                'expenses' => [
                    ['2026-06-15', 'Kommunale avgifter', ExpenseType::Operating, 3_100, 'Kommunale avgifter'],
                    ['2026-03-20', 'Forsikring', ExpenseType::Operating, 2_400, 'Innboforsikring'],
                ],
            ],
            [
                'name' => 'Damsgårdsveien 88', 'postal' => '5160', 'type' => '3-roms', 'area' => 64, 'rooms' => 3,
                'tenant' => null, 'since' => null, 'rent' => 16_400, 'status' => 'ledig',
                'purchase' => ['2015-04-01', 3_800_000],
                'expenses' => [
                    ['2026-06-19', 'Diverse', ExpenseType::Improvement, 7_400, 'Oppussing — nytt gulv'],
                    ['2026-06-02', 'Annonsering', ExpenseType::Operating, 1_800, 'Annonse på Finn'],
                ],
            ],
        ];

        foreach ($apartments as $a) {
            $property = Property::create([
                'name' => $a['name'],
                'address' => $a['name'],
                'postal_code' => $a['postal'],
                'city' => 'Bergen',
                'property_type' => 'Leilighet',
                'purchase_date' => $a['purchase'][0],
                'purchase_price_ore' => $a['purchase'][1] * 100,
            ]);

            // Frittstående: a single unit named after the property (no bruksenhet code).
            $unit = $this->makeUnit($property, [
                'name' => $a['name'], 'code' => null, 'type' => $a['type'], 'area' => $a['area'], 'rooms' => $a['rooms'],
                'tenant' => $a['tenant'], 'since' => $a['since'], 'rent' => $a['rent'], 'status' => $a['status'],
            ]);

            foreach ($a['expenses'] as [$date, $category, $type, $kr, $desc]) {
                Expense::create([
                    'property_id' => $property->id,
                    'unit_id' => $unit->id,
                    'date' => $date,
                    'amount_ore' => $kr * 100,
                    'category' => $category,
                    'type' => $type->value,
                    'description' => $desc,
                    'income_year' => 2026,
                    'created_by' => $this->userId,
                ]);
            }
        }
    }

    /** Create a unit with its tenancy, 2026 rent income, and optional own expense. */
    private function makeUnit(Property $property, array $u): Unit
    {
        $unit = Unit::create([
            'property_id' => $property->id,
            'name' => $u['name'],
            'code' => $u['code'] ?? null,
            'unit_type' => $u['type'],
            'area_sqm' => $u['area'],
            'rooms' => $u['rooms'],
        ]);

        $tenancy = null;
        if ($u['status'] !== 'ledig') {
            $tenant = Tenant::create(['name' => $u['tenant']]);
            $tenancy = Tenancy::create([
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'starts_on' => $u['since'],
                'monthly_rent_ore' => $u['rent'] * 100,
                'deposit_ore' => $u['rent'] * 3 * 100,
            ]);
        }

        // Income for 2026: occupied units run Jan–Jun; a vacant unit only Jan–Mar;
        // the restanse unit's June rent is outstanding (received_on null).
        $lastMonth = $u['status'] === 'ledig' ? 3 : 6;
        for ($month = 1; $month <= $lastMonth; $month++) {
            $outstanding = $u['status'] === 'restanse' && $month === 6;
            Income::create([
                'unit_id' => $unit->id,
                'tenancy_id' => $tenancy?->id,
                'period_year' => 2026,
                'period_month' => $month,
                'amount_ore' => $u['rent'] * 100,
                'received_on' => $outstanding ? null : CarbonImmutable::create(2026, $month, 1),
                'income_year' => 2026,
            ]);
        }

        if (isset($u['expense'])) {
            [$date, $category, $type, $kr, $desc] = $u['expense'];
            Expense::create([
                'property_id' => $property->id,
                'unit_id' => $unit->id,
                'date' => $date,
                'amount_ore' => $kr * 100,
                'category' => $category,
                'type' => $type->value,
                'description' => $desc,
                'income_year' => 2026,
                'created_by' => $this->userId,
            ]);
        }

        return $unit;
    }

    private function seedTrips(): void
    {
        $byName = Property::pluck('id', 'name');

        $trips = [
            ['2026-06-24', 'Befaring bad', 'Svaneviksveien 85', 18],
            ['2026-06-19', 'Visning ny leietaker', 'Damsgårdsveien 88', 12],
            ['2026-06-12', 'Henting materialer', null, 34],
            ['2026-06-07', 'Møte med leietaker', 'Svaneviksveien 85', 9],
            ['2026-06-05', 'Møte regnskapsfører', null, 9],
            ['2026-05-28', 'Inspeksjon etter flytting', 'Damsgårdsveien 88', 12],
            ['2026-05-21', 'Levering nøkler', 'Blekenberg 36', 8],
            ['2026-05-14', 'Rørlegger oppfølging', 'Svaneviksveien 85', 18],
            // April/May trips that line up with the AutoSync toll passings, so the
            // bompenger-mot-kjørebok matching has something to match against.
            ['2026-05-06', 'Møte med leietaker', 'Svaneviksveien 85', 9],
            ['2026-04-29', 'Tilsyn leilighet', 'Blekenberg 36', 8],
            ['2026-04-24', 'Visning', 'Damsgårdsveien 88', 12],
            ['2026-04-22', 'Befaring leil. 2', 'Svaneviksveien 85', 18],
        ];

        $rate = (int) config('forvalter.tax_defaults.mileage_rate_ore_per_km');

        foreach ($trips as [$date, $purpose, $propertyName, $km]) {
            Trip::create([
                'property_id' => $propertyName ? $byName[$propertyName] : null,
                'date' => $date,
                'purpose' => $purpose,
                'distance_km' => $km,
                'rate_ore_per_km' => $rate,
                'deduction_ore' => $km * $rate,
                'source' => TripSource::Web->value,
                'income_year' => 2026,
                'created_by' => $this->userId,
            ]);
        }
    }
}
