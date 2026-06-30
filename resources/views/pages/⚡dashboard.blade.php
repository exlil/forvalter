<?php

use App\Enums\UnitStatus;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Property;
use App\Models\Trip;
use App\Models\Unit;
use App\Support\Money;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public function with(): array
    {
        $year = (int) (Income::max('period_year') ?? now()->year);
        $month = (int) (Income::where('period_year', $year)->max('period_month') ?? now()->month);

        $incomeOf = fn (int $m) => (int) Income::where('period_year', $year)->where('period_month', $m)
            ->whereNotNull('received_on')->sum('amount_ore');
        $expenseOf = fn (int $m) => (int) Expense::whereYear('date', $year)->whereMonth('date', $m)->sum('amount_ore');

        $incomeMonth = new Money($incomeOf($month));
        $expenseMonth = new Money($expenseOf($month));
        $net = $incomeMonth->subtract($expenseMonth);
        $outstanding = new Money((int) Income::where('income_year', $year)->whereNull('received_on')->sum('amount_ore'));

        // Monthly net series → SVG sparkline for the hero card.
        $series = collect(range(1, $month))->map(fn (int $m) => $incomeOf($m) - $expenseOf($m))->values()->all();
        [$sparkLine, $sparkArea, $sparkLast, $monthLabels] = $this->sparkline($series, $year);

        $prevNet = $month > 1 ? ($incomeOf($month - 1) - $expenseOf($month - 1)) : null;
        $deltaPct = $prevNet && $prevNet != 0 ? round(($net->ore - $prevNet) / abs($prevNet) * 100, 1) : null;

        $felleskostnaderMonth = new Money((int) Expense::whereNull('unit_id')
            ->whereYear('date', $year)->whereMonth('date', $month)->sum('amount_ore'));

        // ── Året så langt (year to date, Jan..current month) ──
        $ytdIncome = (int) Income::where('income_year', $year)->whereNotNull('received_on')->sum('amount_ore');
        $ytdExpense = (int) Expense::where('income_year', $year)->sum('amount_ore');
        $ytdNet = $ytdIncome - $ytdExpense;
        $monthsElapsed = max($month, 1);
        $annualizedNet = $ytdNet / $monthsElapsed * 12;

        // Prior-year same-period net, for the "mot i fjor" delta.
        $prevIncome = (int) Income::where('income_year', $year - 1)->where('period_month', '<=', $month)
            ->whereNotNull('received_on')->sum('amount_ore');
        $prevExpense = (int) Expense::where('income_year', $year - 1)->whereMonth('date', '<=', $month)->sum('amount_ore');
        $prevNetYtd = $prevIncome - $prevExpense;
        $ytdDelta = $prevNetYtd != 0 ? round(($ytdNet - $prevNetYtd) / abs($prevNetYtd) * 100, 1) : null;

        // ── Inntekt vs. utgift, monthly bars ──
        $chartMonths = collect(range(1, $month))->map(fn (int $m) => [
            'label' => Carbon::create($year, $m, 1)->locale('nb')->isoFormat('MMM'),
            'inc' => $incomeOf($m),
            'exp' => $expenseOf($m),
        ]);
        $chartPeak = max((int) $chartMonths->flatMap(fn ($x) => [$x['inc'], $x['exp']])->max(), 1);
        $chartMonths = $chartMonths->map(fn ($x) => [
            'label' => $x['label'],
            'incH' => (int) round($x['inc'] / $chartPeak * 150),
            'expH' => (int) round($x['exp'] / $chartPeak * 150),
        ]);

        // ── Utgiftsfordeling for the current month (by kategori) ──
        $distRaw = Expense::whereYear('date', $year)->whereMonth('date', $month)->get()
            ->groupBy(fn (Expense $e) => $e->category ?: 'Diverse')
            ->map(fn ($g) => (int) $g->sum(fn (Expense $e) => $e->amount_ore->ore))
            ->sortDesc();
        $distTotal = max((int) $distRaw->sum(), 1);
        $distribution = $distRaw->take(6)->map(fn (int $amt, string $cat) => [
            'cat' => $cat,
            'amount' => new Money($amt),
            'pct' => (int) round($amt / $distTotal * 100),
        ])->values();

        // Eiendommer: bygård rows (→ building) + frittstående rows (→ unit).
        $properties = Property::withCount('units')->with(['units.currentTenancy'])
            ->orderByDesc('units_count')->orderBy('name')->get();

        // Direkteavkastning (annualised net ÷ total cost basis / inngangsverdi).
        $totalCostBasis = (int) $properties->sum(fn (Property $p) => $p->costBasis()->ore);
        $yield = $totalCostBasis > 0 ? round($annualizedNet / $totalCostBasis * 100, 1) : null;

        $allUnits = $properties->flatMap->units;
        $unitCount = $allUnits->count();
        $rentedCount = $allUnits->filter(fn (Unit $u) => $u->status() !== UnitStatus::Vacant)->count();

        $tone = fn (UnitStatus $s) => match ($s) {
            UnitStatus::Rented => 'bg-positive',
            UnitStatus::Arrears => 'bg-negative',
            UnitStatus::Vacant => 'bg-vacant',
        };

        $eiendomRows = $properties->map(function (Property $p) use ($tone) {
            if ($p->isBuilding()) {
                $statuses = $p->units->map->status();
                $dot = $statuses->contains(UnitStatus::Arrears) ? 'bg-negative'
                    : ($statuses->contains(UnitStatus::Vacant) ? 'bg-vacant' : 'bg-positive');

                return ['name' => $p->name, 'sub' => 'Bygård · '.$p->units_count.' enheter',
                    'leie' => $p->monthlyRent()->format(), 'dot' => $dot, 'url' => route('properties.show', $p)];
            }

            $unit = $p->units->first();

            return ['name' => $p->name, 'sub' => $unit?->currentTenancy?->tenant?->name ?? 'Ledig',
                'leie' => $unit?->currentTenancy?->monthly_rent_ore?->format() ?? '—',
                'dot' => $tone($unit?->status() ?? UnitStatus::Vacant), 'url' => route('units.show', $unit)];
        });

        return [
            'monthLabel' => Carbon::create($year, $month, 1)->locale('nb')->isoFormat('MMMM YYYY'),
            'year' => $year,
            'net' => $net,
            'incomeMonth' => $incomeMonth,
            'expenseMonth' => $expenseMonth,
            'outstanding' => $outstanding,
            'sparkLine' => $sparkLine,
            'sparkArea' => $sparkArea,
            'sparkLast' => $sparkLast,
            'monthLabels' => $monthLabels,
            'deltaPct' => $deltaPct,
            'eiendomRows' => $eiendomRows,
            'unitCount' => $unitCount,
            'rentedCount' => $rentedCount,
            'occupancy' => $unitCount ? (int) round($rentedCount / $unitCount * 100) : 0,
            'felleskostnaderMonth' => $felleskostnaderMonth,
            'kmYear' => (int) Trip::where('income_year', $year)->sum('distance_km'),
            'deduction' => new Money((int) Trip::where('income_year', $year)->sum('deduction_ore')),

            // Året så langt
            'ytdNet' => new Money($ytdNet),
            'ytdIncome' => new Money($ytdIncome),
            'ytdExpense' => new Money($ytdExpense),
            'ytdAvg' => new Money((int) round($ytdNet / $monthsElapsed)),
            'ytdProjected' => new Money((int) round($annualizedNet)),
            'ytdDelta' => $ytdDelta,
            'yield' => $yield,
            'monthsElapsed' => $monthsElapsed,
            'yearPct' => (int) round($monthsElapsed / 12 * 100),
            'firstMonthLabel' => Carbon::create($year, 1, 1)->locale('nb')->isoFormat('MMM'),
            'lastMonthLabel' => Carbon::create($year, $month, 1)->locale('nb')->isoFormat('MMM'),

            // Charts
            'chartMonths' => $chartMonths,
            'distribution' => $distribution,
        ];
    }

    /**
     * Build SVG path strings for the hero sparkline from a net-per-month series.
     *
     * @param  array<int,int>  $vals  net (øre) per month, Jan..current
     * @return array{0:string,1:string,2:array{x:float,y:float},3:array<int,string>}
     */
    private function sparkline(array $vals, int $year): array
    {
        $w = 340.0;
        $h = 88.0;
        $pad = 8.0;
        $n = count($vals);

        $labels = collect(range(1, $n))
            ->map(fn (int $m) => Carbon::create($year, $m, 1)->locale('nb')->isoFormat('MMM'))
            ->all();

        if ($n === 0) {
            return ['', '', ['x' => $w, 'y' => $h / 2], $labels];
        }

        $min = min($vals);
        $max = max($vals);
        $range = max($max - $min, 1);

        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $n === 1 ? $w : round($i / ($n - 1) * $w, 1);
            $y = round($h - $pad - (($v - $min) / $range) * ($h - 2 * $pad), 1);
            $pts[] = [$x, $y];
        }

        $line = 'M'.implode(' L', array_map(fn ($p) => "{$p[0]},{$p[1]}", $pts));
        $area = $line." L{$w},{$h} L0,{$h} Z";
        $last = end($pts);

        return [$line, $area, ['x' => $last[0], 'y' => $last[1]], $labels];
    }
};
?>

<div>
    {{-- Hero card --}}
    <div class="relative overflow-hidden rounded-[20px] p-7 md:p-[42px]" style="background:#14161B; color:#EEF1F6;">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(115% 150% at 100% -10%, rgba(44,92,230,.26), transparent 52%);"></div>
        <div class="relative flex flex-col gap-9 md:flex-row md:items-end md:justify-between md:gap-12">
            <div>
                <div class="flex items-center gap-2.5 text-[12px] uppercase tracking-[0.15em]" style="color:#9097A6;">
                    <span class="h-[1.5px] w-5 bg-terra"></span>Netto resultat · {{ $monthLabel }}
                </div>
                <div class="tnum font-display mt-5 text-[52px] font-bold leading-none tracking-[-0.035em] md:text-[80px]">{{ $net->format() }}</div>
                @if ($deltaPct !== null)
                    <div class="mt-5 flex items-center gap-3.5">
                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1.5 text-[13px] font-semibold"
                            style="color:{{ $deltaPct >= 0 ? '#5CD99F' : '#F2A2A2' }}; background:{{ $deltaPct >= 0 ? 'rgba(16,159,110,.18)' : 'rgba(214,69,69,.18)' }};">
                            {{ $deltaPct >= 0 ? '▲' : '▼' }} {{ \App\Support\Format::number(abs($deltaPct), 1) }} %
                        </span>
                        <span class="text-[13.5px]" style="color:#9097A6;">mot forrige måned</span>
                    </div>
                @endif
            </div>
            <div class="hidden shrink-0 md:block">
                <svg width="340" height="88" viewBox="0 0 340 88" style="display:block;">
                    <defs><linearGradient id="heroFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5B86F2" stop-opacity="0.32"/><stop offset="1" stop-color="#5B86F2" stop-opacity="0"/></linearGradient></defs>
                    @if ($sparkArea)
                        <path d="{{ $sparkArea }}" fill="url(#heroFill)"></path>
                        <path d="{{ $sparkLine }}" fill="none" stroke="#5B86F2" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"></path>
                        <circle cx="{{ $sparkLast['x'] }}" cy="{{ $sparkLast['y'] }}" r="5" fill="#5B86F2" stroke="#14161B" stroke-width="2.5"></circle>
                    @endif
                </svg>
                <div class="flex justify-between px-1 pt-2 text-[11px]" style="color:#6E7686; width:340px;">
                    @foreach ($monthLabels as $m)<span>{{ $m }}</span>@endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="mt-1.5 grid grid-cols-2 border-b border-line md:grid-cols-4">
        <x-stat class="py-6" label="Inntekter · {{ \Illuminate\Support\Str::lower($monthLabel) }}" :value="$incomeMonth->format()"
            sub="{{ $rentedCount }} av {{ $unitCount }} utleid" />
        <x-stat class="py-6 md:border-l md:border-line md:pl-[30px]" label="Utgifter · {{ \Illuminate\Support\Str::lower($monthLabel) }}"
            :value="$expenseMonth->format()"
            sub="{{ $felleskostnaderMonth->isZero() ? 'hittil i måneden' : 'inkl. felleskostnader' }}" />
        <x-stat class="border-t border-line py-6 md:border-l md:border-t-0 md:pl-[30px]" label="Utestående" tone="negative" :value="$outstanding->format()"
            sub="{{ $outstanding->isZero() ? 'alt betalt' : 'restanse' }}" />
        <x-stat class="border-t border-line py-6 md:border-l md:border-t-0 md:pl-[30px]" label="Utleiegrad" :value="$occupancy.' %'"
            sub="{{ $rentedCount }} av {{ $unitCount }} enheter" />
    </div>

    {{-- Året så langt --}}
    <div class="mt-8 rounded-2xl border border-panel-line bg-panel px-6 py-6 md:px-7">
        <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-2.5 text-[12px] uppercase tracking-[0.1em] text-faint"><span class="h-[1.5px] w-3.5 bg-terra"></span>Året så langt · {{ $year }}</div>
            <div class="text-[12.5px] text-muted">{{ $firstMonthLabel }}–{{ $lastMonthLabel }} · {{ $monthsElapsed }} av 12 mnd</div>
        </div>
        <div class="grid grid-cols-2 gap-y-5 md:grid-cols-5 md:gap-y-0">
            <div class="md:pr-6">
                <div class="text-[11.5px] uppercase tracking-[0.08em] text-faint">Netto hittil</div>
                <div class="tnum font-display mt-2.5 whitespace-nowrap text-[26px] font-bold tracking-[-0.025em] text-positive md:text-[30px]">{{ $ytdNet->format() }}</div>
                @if ($ytdDelta !== null)
                    <div class="mt-1.5 text-[12.5px] {{ $ytdDelta >= 0 ? 'text-positive' : 'text-negative' }}">{{ $ytdDelta >= 0 ? '▲' : '▼' }} {{ \App\Support\Format::number(abs($ytdDelta), 1) }} % mot i fjor</div>
                @else
                    <div class="mt-1.5 text-[12.5px] text-faint">netto resultat</div>
                @endif
            </div>
            <div class="md:border-l md:border-panel-line md:px-6">
                <div class="text-[11.5px] uppercase tracking-[0.08em] text-faint">Inntekter</div>
                <div class="tnum font-display mt-2.5 whitespace-nowrap text-[24px] font-semibold tracking-[-0.02em] md:text-[26px]">{{ $ytdIncome->format() }}</div>
                <div class="mt-1.5 text-[12.5px] text-faint">leie + sluttoppgjør</div>
            </div>
            <div class="md:border-l md:border-panel-line md:px-6">
                <div class="text-[11.5px] uppercase tracking-[0.08em] text-faint">Utgifter</div>
                <div class="tnum font-display mt-2.5 whitespace-nowrap text-[24px] font-semibold tracking-[-0.02em] md:text-[26px]">{{ $ytdExpense->format() }}</div>
                <div class="mt-1.5 text-[12.5px] text-faint">inkl. felleskostnader</div>
            </div>
            <div class="md:border-l md:border-panel-line md:px-6">
                <div class="text-[11.5px] uppercase tracking-[0.08em] text-faint">Snitt netto / mnd</div>
                <div class="tnum font-display mt-2.5 whitespace-nowrap text-[24px] font-semibold tracking-[-0.02em] md:text-[26px]">{{ $ytdAvg->format() }}</div>
                <div class="mt-1.5 text-[12.5px] text-faint">over {{ $monthsElapsed }} måneder</div>
            </div>
            <div class="md:border-l md:border-panel-line md:pl-6">
                <div class="text-[11.5px] uppercase tracking-[0.08em] text-faint">Direkteavkastning</div>
                <div class="tnum font-display mt-2.5 whitespace-nowrap text-[24px] font-semibold tracking-[-0.02em] md:text-[26px]">{{ $yield !== null ? \App\Support\Format::number($yield, 1).' %' : '—' }}</div>
                <div class="mt-1.5 text-[12.5px] text-faint">annualisert</div>
            </div>
        </div>
        <div class="mt-6">
            <div class="mb-2 flex justify-between text-[11.5px] text-faint">
                <span>Året {{ $year }}</span><span>{{ $yearPct }} % gjennomført · prognose netto {{ $ytdProjected->format() }}</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-panel-line">
                <div class="h-full rounded-full bg-terra" style="width: {{ $yearPct }}%"></div>
            </div>
        </div>
    </div>

    {{-- Lists --}}
    <div class="grid grid-cols-1 gap-10 pt-9 md:grid-cols-[1.4fr_1fr] md:gap-[60px] md:pt-11">
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-[12px] uppercase tracking-[0.1em] text-faint"><span class="h-[1.5px] w-3.5 bg-terra"></span>Eiendommer</div>
                <a href="{{ route('properties.index') }}" class="text-[13px] font-semibold text-terra">Se alle →</a>
            </div>
            @foreach ($eiendomRows as $row)
                <a href="{{ $row['url'] }}" class="flex items-center justify-between border-b border-line py-4">
                    <div class="flex items-center gap-3.5">
                        <span class="size-2 rounded-full {{ $row['dot'] }}"></span>
                        <div>
                            <div class="text-base font-medium">{{ $row['name'] }}</div>
                            <div class="mt-0.5 text-xs text-faint">{{ $row['sub'] }}</div>
                        </div>
                    </div>
                    <div class="text-base font-semibold">{{ $row['leie'] }}</div>
                </a>
            @endforeach
        </div>

        <div>
            <div class="mb-1.5 flex items-center gap-2.5 text-[12px] uppercase tracking-[0.1em] text-faint"><span class="h-[1.5px] w-3.5 bg-terra"></span>Kjørebok {{ $year }}</div>
            <div class="border-b border-line pb-6 pt-5">
                <div class="tnum font-display text-5xl font-bold leading-none tracking-tight">
                    {{ \App\Support\Format::number($kmYear) }} <span class="font-sans text-xl font-medium text-muted">km</span>
                </div>
                <div class="mt-3 text-[15px] font-medium text-positive">Beregnet fradrag {{ $deduction->format() }}</div>
            </div>
            <a href="{{ route('trips.index') }}" class="mt-4 inline-block text-[13px] font-semibold text-terra">Åpne kjørebok →</a>
        </div>
    </div>

    {{-- Charts: inntekt vs. utgift + utgiftsfordeling --}}
    <div class="grid grid-cols-1 gap-10 pt-12 md:grid-cols-[1.5fr_1fr] md:gap-[60px]">
        <div>
            <div class="mb-4 flex items-baseline justify-between">
                <div class="flex items-center gap-2.5 text-[12px] uppercase tracking-[0.1em] text-faint"><span class="h-[1.5px] w-3.5 bg-terra"></span>Inntekt vs. utgift · {{ $year }}</div>
                <div class="text-[13px] text-muted">Resultat hittil <span class="font-semibold text-positive">{{ $ytdNet->format() }}</span></div>
            </div>
            <div class="mb-4 flex gap-4">
                <span class="flex items-center gap-1.5 text-xs text-muted"><span class="size-2.5 rounded-[2px] bg-positive"></span>Inntekt</span>
                <span class="flex items-center gap-1.5 text-xs text-muted"><span class="size-2.5 rounded-[2px]" style="background:#C9CED8;"></span>Utgift</span>
            </div>
            <div class="flex items-end justify-between px-1">
                @forelse ($chartMonths as $mo)
                    <div class="flex flex-col items-center gap-2.5">
                        <div class="flex h-[150px] items-end gap-[5px]">
                            <div class="w-[18px] rounded-t-[3px] bg-positive" style="height: {{ max($mo['incH'], 2) }}px"></div>
                            <div class="w-[18px] rounded-t-[3px]" style="height: {{ max($mo['expH'], 2) }}px; background:#C9CED8;"></div>
                        </div>
                        <div class="text-xs text-faint">{{ $mo['label'] }}</div>
                    </div>
                @empty
                    <div class="py-10 text-sm text-muted">Ingen tall registrert for {{ $year }} ennå.</div>
                @endforelse
            </div>
        </div>

        <div>
            <div class="mb-5 flex items-center gap-2.5 text-[12px] uppercase tracking-[0.1em] text-faint"><span class="h-[1.5px] w-3.5 bg-terra"></span>Utgiftsfordeling · {{ \Illuminate\Support\Str::lower($lastMonthLabel) }}</div>
            @forelse ($distribution as $bar)
                <div class="mb-4">
                    <div class="mb-1.5 flex justify-between text-[13px]">
                        <span class="text-ink-soft">{{ $bar['cat'] }}</span><span class="text-muted">{{ $bar['amount']->format() }}</span>
                    </div>
                    <div class="h-[7px] overflow-hidden rounded-[4px] bg-chip">
                        <div class="h-full rounded-[4px] bg-terra" style="width: {{ $bar['pct'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">Ingen utgifter registrert denne måneden.</p>
            @endforelse
        </div>
    </div>
</div>
