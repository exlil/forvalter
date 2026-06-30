<?php

use App\Enums\UnitStatus;
use App\Models\Income;
use App\Models\Property;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public Property $property;

    public function mount(Property $property)
    {
        $property->loadCount('units')->load(['units.currentTenancy.tenant']);

        // Frittstående (single unit) has no building view — go straight to the unit.
        if (! $property->isBuilding()) {
            return $this->redirect(route('units.show', $property->units->first()), navigate: true);
        }

        $this->property = $property;
    }

    public function with(): array
    {
        $year = (int) (Income::max('period_year') ?? now()->year);
        $month = (int) (Income::where('period_year', $year)->max('period_month') ?? now()->month);

        $samlet = $this->property->monthlyRent();
        $felles = $this->property->felleskostnaderForMonth($year, $month);

        return [
            'year' => $year,
            'samletLeie' => $samlet,
            'fellesMnd' => $felles,
            'nettoBygg' => $samlet->subtract($felles),
            'fellesYear' => $this->property->felleskostnaderForYear($year),
            'felleslines' => $this->property->felleskostnaderForMonth($year, $month)->isZero()
                ? collect()
                : $this->property->felleskostnader()->with('document')->whereYear('date', $year)->whereMonth('date', $month)
                    ->orderByDesc('amount_ore')->get(),
        ];
    }
};
?>

@php
    $dotClass = fn (UnitStatus $s) => match ($s) {
        UnitStatus::Rented => 'bg-positive',
        UnitStatus::Arrears => 'bg-terra',
        UnitStatus::Vacant => 'bg-vacant',
    };
@endphp

<div>
    <a href="{{ route('properties.index') }}" class="text-sm text-muted hover:text-ink">← Boliger</a>

    <div class="mb-9 mt-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <div class="flex items-center gap-3.5">
                <h1 class="text-3xl font-bold tracking-tight md:text-[34px]">{{ $property->name }}</h1>
                <span class="inline-flex items-center rounded-full bg-vacant-soft px-2.5 py-1 text-xs font-semibold text-ink-soft">Bygård · {{ $property->units_count }} enheter</span>
            </div>
            <div class="mt-2 text-[15px] text-muted">{{ $property->address }}, {{ $property->postal_code }} {{ $property->city }}</div>
        </div>
        <div class="flex gap-2.5">
            <button type="button" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold">Rediger</button>
            <a href="{{ route('expenses.create') }}" class="rounded-[10px] bg-terra px-4 py-2.5 text-sm font-semibold text-white">+ Felleskostnad</a>
        </div>
    </div>

    <div class="grid grid-cols-2 border-y border-line md:grid-cols-4">
        <x-stat class="py-5 md:py-6" label="Samlet leie · mnd" :value="$samletLeie->format()" />
        <x-stat class="py-5 md:border-l md:border-line md:py-6 md:pl-[30px]" label="Felleskostnader · mnd" tone="terra" :value="$fellesMnd->format()" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Netto bygg · mnd" tone="positive" :value="$nettoBygg->format()" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Felleskostnader {{ $year }}" :value="$fellesYear->format()" />
    </div>

    <div class="grid grid-cols-1 gap-10 pt-8 md:grid-cols-[1.4fr_1fr] md:gap-14 md:pt-10">
        <div>
            <div class="mb-2 text-[13px] uppercase tracking-[0.08em] text-faint">Enheter</div>
            @foreach ($property->units as $unit)
                @php($status = $unit->status())
                <a href="{{ route('units.show', $unit) }}" class="flex items-center justify-between border-b border-line py-4">
                    <div class="flex items-center gap-3.5">
                        <span class="size-2 rounded-full {{ $dotClass($status) }}"></span>
                        <div>
                            <div class="text-base font-medium">{{ $unit->name }}@if ($unit->code) <span class="text-faint">({{ $unit->code }})</span>@endif</div>
                            <div class="mt-0.5 text-xs text-faint">{{ $unit->unit_type }} · {{ $unit->currentTenancy?->tenant?->name ?? 'Ledig' }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-[18px]">
                        <x-status-pill :status="$status" />
                        <div class="w-[90px] text-right text-base font-semibold">{{ $unit->currentTenancy?->monthly_rent_ore?->format() ?? '—' }}</div>
                    </div>
                </a>
            @endforeach
        </div>

        <div>
            <div class="mb-2 text-[13px] uppercase tracking-[0.08em] text-faint">Felleskostnader · mnd</div>
            <x-card class="px-[22px] py-1">
                @forelse ($felleslines as $line)
                    <div class="flex items-start justify-between border-b border-line py-3 text-sm">
                        <div>
                            <div class="text-ink-soft">{{ $line->description ?? $line->category }}</div>
                            <div class="mt-0.5 flex items-center gap-2 text-[11.5px] text-faint">
                                <span class="rounded-full bg-teal-soft px-1.5 py-0.5 font-medium text-teal">{{ $line->type->label() }}</span>
                                @if ($line->document)
                                    <a href="{{ route('documents.show', $line->document) }}" target="_blank" class="inline-flex items-center gap-1 text-terra hover:opacity-80">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15V8a5 5 0 0 0-10 0v9a3 3 0 0 0 6 0V9"/></svg>
                                        Bilag
                                    </a>
                                @endif
                            </div>
                        </div>
                        <span class="font-semibold">{{ $line->amount_ore->format() }}</span>
                    </div>
                @empty
                    <div class="py-3.5 text-sm text-muted">Ingen felleskostnader registrert denne måneden.</div>
                @endforelse
                <div class="flex justify-between py-4">
                    <span class="text-sm font-semibold">Sum felles</span>
                    <span class="text-base font-bold">{{ $fellesMnd->format() }}</span>
                </div>
            </x-card>
            <p class="mt-3.5 px-1 text-[13px] leading-relaxed text-faint">
                Felleskostnader holdes på byggnivå og fordeles ikke ned på den enkelte enhet.
            </p>
        </div>
    </div>
</div>
