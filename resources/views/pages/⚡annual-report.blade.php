<?php

use App\Models\Expense;
use App\Models\Income;
use App\Models\Trip;
use App\Services\TaxExport\AnnualReport;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public int $year;

    public function mount(): void
    {
        $this->year = $this->availableYears()->first() ?? (int) now()->year;
    }

    private function availableYears()
    {
        return collect([
            ...Expense::distinct()->pluck('income_year'),
            ...Income::distinct()->pluck('income_year'),
            ...Trip::distinct()->pluck('income_year'),
        ])->filter()->unique()->sortDesc()->values();
    }

    public function with(): array
    {
        return [
            'r' => (new AnnualReport($this->year))->build(),
            'years' => $this->availableYears(),
        ];
    }
};
?>

@php $fmt = fn ($m) => $m->format(decimals: true); $fmtWhole = fn ($m) => $m->format(); $t = $r['totals']; $doc = $r['documentation']; @endphp

<div>
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight md:text-[34px]">Årsoppgjør</h1>
            <p class="mb-0 mt-1 text-[15px] text-muted">Underlag for skattemeldingen — utleie som kapitalinntekt. Ikke innsendt.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <select wire:model.live="year" class="appearance-none rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-sm font-semibold outline-none focus:border-terra">
                @foreach ($years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Export buttons --}}
    <div class="mt-5 flex flex-wrap gap-2.5">
        <a href="{{ route('arsoppgjor.pdf', ['year' => $year]) }}"
            class="inline-flex items-center gap-2 rounded-[10px] bg-terra px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            PDF
        </a>
        <a href="{{ route('arsoppgjor.csv', ['year' => $year]) }}"
            class="inline-flex items-center gap-2 rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-faint">
            Regneark
        </a>
        <a href="{{ route('arsoppgjor.bilag', ['year' => $year]) }}"
            class="inline-flex items-center gap-2 rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-faint">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            Alle bilag ({{ $doc['with_doc'] }})
        </a>
    </div>

    {{-- Summary --}}
    <div class="mt-7 grid grid-cols-2 border-y border-line md:grid-cols-4">
        <x-stat class="py-5 md:py-6" label="Leieinntekt {{ $year }}" :value="$fmtWhole($t['income'])" />
        <x-stat class="py-5 md:border-l md:border-line md:py-6 md:pl-[30px]" label="Sum fradrag" :value="$fmtWhole($t['deductible'])" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Netto resultat" tone="{{ $t['net']->ore >= 0 ? 'positive' : 'default' }}" :value="$fmtWhole($t['net'])" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Per eier (50 %)" tone="teal" :value="$fmtWhole($t['per_owner'])" />
    </div>

    {{-- Documentation status --}}
    <div class="mt-5 flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-center md:justify-between
        {{ $doc['missing'] > 0 ? 'border-panel-line bg-panel' : 'border-positive-line bg-positive-soft' }}">
        <div class="text-[13.5px]">
            <span class="font-semibold">Dokumentasjon:</span>
            {{ $doc['with_doc'] }} av {{ $doc['total_expenses'] }} utgifter har bilag.
            @if ($doc['missing'] > 0)
                <span class="text-terra">{{ $doc['missing'] }} mangler — last opp i Innboks for komplett underlag.</span>
            @else
                <span class="text-positive-strong">Alt er dokumentert ✓</span>
            @endif
        </div>
        @if ($doc['with_doc'] > 0)
            <a href="{{ route('arsoppgjor.bilag', ['year' => $year]) }}" class="shrink-0 text-[13px] font-semibold text-terra hover:opacity-80">Last ned alle bilag som ZIP →</a>
        @endif
    </div>

    {{-- Per property --}}
    <div class="mb-2 mt-9 text-[13px] uppercase tracking-[0.08em] text-faint">Resultat per eiendom</div>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($r['properties'] as $p)
            <x-card class="flex flex-col p-5 md:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[17px] font-semibold">{{ $p['name'] }}</div>
                        <div class="mt-0.5 text-[12.5px] text-faint">{{ $p['is_building'] ? 'Bygård' : 'Frittstående' }}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-[11.5px] uppercase tracking-[0.06em] text-faint">Netto</div>
                        <div class="tnum whitespace-nowrap text-xl font-bold {{ $p['net']->ore >= 0 ? 'text-positive' : 'text-terra' }}">{{ $fmt($p['net']) }}</div>
                    </div>
                </div>

                <div class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-muted">Leieinntekt</span><span class="font-medium">{{ $fmt($p['income']) }}</span></div>
                    @if ($p['income_outstanding']->ore > 0)
                        <div class="flex justify-between text-[12.5px]"><span class="text-faint">herav utestående</span><span class="text-faint">{{ $fmt($p['income_outstanding']) }}</span></div>
                    @endif
                    @if ($p['costs']['maintenance']->ore > 0)<div class="flex justify-between"><span class="text-muted">Vedlikehold</span><span>−{{ $fmt($p['costs']['maintenance']) }}</span></div>@endif
                    @if ($p['costs']['operating']->ore > 0)<div class="flex justify-between"><span class="text-muted">Drift</span><span>−{{ $fmt($p['costs']['operating']) }}</span></div>@endif
                    @if ($p['costs']['finance']->ore > 0)<div class="flex justify-between"><span class="text-muted">Finans (renter)</span><span>−{{ $fmt($p['costs']['finance']) }}</span></div>@endif
                    @if ($p['mileage']['deduction']->ore > 0)<div class="flex justify-between"><span class="text-muted">Kjøring ({{ $p['mileage']['km'] }} km)</span><span>−{{ $fmt($p['mileage']['deduction']) }}</span></div>@endif
                    @if ($p['depreciation']->ore > 0)<div class="flex justify-between"><span class="text-muted">Avskrivning</span><span>−{{ $fmt($p['depreciation']) }}</span></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 font-semibold"><span>Sum fradrag</span><span>−{{ $fmt($p['deductible_total']) }}</span></div>
                </div>

                @if ($p['improvement']->ore > 0)
                    <div class="mt-3 rounded-lg bg-panel px-3 py-2 text-[12.5px] text-ink-soft">
                        Påkostning {{ $fmt($p['improvement']) }} — aktiveres på inngangsverdi ({{ $fmt($p['cost_basis']) }}), ikke fradrag i år.
                    </div>
                @endif

                @if (count($p['categories']))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($p['categories'] as $c)
                            <span class="rounded-full border border-line-strong px-2.5 py-1 text-[11.5px] text-muted">{{ $c['label'] }} · {{ $fmt($c['amount']) }}</span>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>

    @if ($r['felles_mileage']['deduction']->ore > 0)
        <div class="mt-4 flex items-center justify-between rounded-xl border border-line bg-surface px-5 py-4">
            <div>
                <div class="text-[15px] font-medium">Felles kjøring</div>
                <div class="mt-0.5 text-xs text-faint">{{ $r['felles_mileage']['km'] }} km · {{ $r['felles_mileage']['trips'] }} turer · ikke knyttet til én eiendom</div>
            </div>
            <div class="text-[15px] font-semibold text-positive">−{{ $fmt($r['felles_mileage']['deduction']) }}</div>
        </div>
    @endif

    <p class="mt-8 text-[12.5px] leading-relaxed text-faint">
        Forvalter er et hjelpeverktøy for å forberede skattemeldingen — ikke skatterådgivning. Kontrollér mot Skatteetaten før innsending.
    </p>
</div>
