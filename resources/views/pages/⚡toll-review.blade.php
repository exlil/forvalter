<?php

use App\Enums\AnalysisStatus;
use App\Enums\ExpenseType;
use App\Models\DocumentAnalysis;
use App\Models\Expense;
use App\Models\Property;
use App\Services\Toll\TollMatcher;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public int $analysisId;

    /** Per-passing include state, keyed by passing index. Defaults to "matched". */
    public array $included = [];

    /** Property for any included passing that has no matched trip of its own. */
    public ?int $fallbackPropertyId = null;

    public bool $initialised = false;
    public bool $booked = false;
    public array $bookedSummary = [];

    public function mount(int $analysis): void
    {
        $this->analysisId = $analysis;

        $a = DocumentAnalysis::findOrFail($analysis);
        if ($a->status === AnalysisStatus::Confirmed) {
            $this->booked = true;
        }
    }

    private function passings(): array
    {
        $a = DocumentAnalysis::find($this->analysisId);

        return $a?->suggested['toll_passings'] ?? [];
    }

    /** Seed include-state once: matched passings start ticked, the rest unticked. */
    private function ensureInitialised(array $rows): void
    {
        if ($this->initialised) {
            return;
        }

        foreach ($rows as $row) {
            $this->included[$row['index']] = $row['matched'];
        }

        $this->fallbackPropertyId ??= collect($rows)
            ->where('matched', true)->whereNotNull('property_id')
            ->groupBy('property_id')->map->count()->sortDesc()->keys()->first()
            ?? Property::orderBy('name')->value('id');

        $this->initialised = true;
    }

    public function toggle(int $index): void
    {
        $this->included[$index] = ! ($this->included[$index] ?? false);
    }

    public function selectMatchedOnly(): void
    {
        foreach (app(TollMatcher::class)->match($this->passings()) as $row) {
            $this->included[$row['index']] = $row['matched'];
        }
    }

    public function book(): void
    {
        $analysis = DocumentAnalysis::findOrFail($this->analysisId);
        if ($analysis->status === AnalysisStatus::Confirmed) {
            return;
        }

        $rows = app(TollMatcher::class)->match($this->passings());

        // Group included passings (in øre) by the property they belong to.
        $byProperty = [];
        $includedIndexes = [];
        foreach ($rows as $row) {
            if (! ($this->included[$row['index']] ?? false)) {
                continue;
            }
            $propertyId = $row['property_id'] ?? $this->fallbackPropertyId;
            if (! $propertyId) {
                continue;
            }
            $byProperty[$propertyId] = ($byProperty[$propertyId] ?? 0) + $row['amount_ore'];
            $includedIndexes[] = $row['index'];
        }

        $byProperty = array_filter($byProperty, fn ($ore) => $ore > 0);
        if (empty($byProperty)) {
            return;
        }

        $s = $analysis->suggested ?? [];
        $date = ! empty($s['date']) ? Carbon::parse($s['date'])->format('Y-m-d') : now()->format('Y-m-d');
        $vendor = $s['vendor'] ?? 'Bompengeoperatør';
        $invoiceRef = $s['invoice_number'] ? " (faktura {$s['invoice_number']})" : '';
        $count = count($includedIndexes);

        $summary = [];
        $firstExpenseId = null;
        foreach ($byProperty as $propertyId => $ore) {
            $property = Property::with('units')->find($propertyId);
            if (! $property) {
                continue;
            }

            $expense = Expense::create([
                'property_id' => $property->id,
                'unit_id' => $this->resolveUnitId($property),
                'document_id' => $analysis->document_id,
                'document_analysis_id' => $analysis->id,
                'date' => $date,
                'amount_ore' => new Money((int) $ore),
                'vat_ore' => new Money(0),
                'vendor' => $vendor,
                'category' => 'Bompenger',
                'type' => ExpenseType::Operating->value,
                'description' => "Bompenger – {$count} passeringer matchet kjørebok{$invoiceRef}",
                'created_by' => Auth::id(),
            ]);

            $firstExpenseId ??= $expense->id;
            $summary[] = ['property' => $property->name, 'amount' => (new Money((int) $ore))->format()];
        }

        $analysis->update([
            'status' => AnalysisStatus::Confirmed->value,
            'confirmed_expense_id' => $firstExpenseId,
            'confirmed_at' => now(),
            'suggested' => [...$s, 'toll_confirmed' => ['indexes' => $includedIndexes, 'total_ore' => array_sum($byProperty)]],
        ]);

        $this->bookedSummary = [
            'total' => (new Money((int) array_sum($byProperty)))->format(),
            'count' => $count,
            'groups' => $summary,
        ];
        $this->booked = true;
    }

    /** Felleskostnad attribution: frittstående → its unit; bygård → building level (null). */
    private function resolveUnitId(Property $property): ?int
    {
        return $property->isBuilding() ? null : $property->units->first()?->id;
    }

    public function with(): array
    {
        $analysis = DocumentAnalysis::with('document')->findOrFail($this->analysisId);
        $s = $analysis->suggested ?? [];
        $rows = app(TollMatcher::class)->match($this->passings());

        $this->ensureInitialised($rows);

        // Attach the live include flag + build the per-property booking preview.
        $rows = array_map(function (array $row) {
            $row['included'] = $this->included[$row['index']] ?? false;

            return $row;
        }, $rows);

        $byProperty = [];
        foreach ($rows as $row) {
            if (! $row['included']) {
                continue;
            }
            $pid = $row['property_id'] ?? $this->fallbackPropertyId;
            $name = $row['property_name'] ?? (Property::find($this->fallbackPropertyId)?->name ?? '—');
            $byProperty[$name] = ($byProperty[$name] ?? 0) + $row['amount_ore'];
        }

        $includedTotal = collect($rows)->where('included', true)->sum('amount_ore');
        $matchedCount = collect($rows)->where('matched', true)->count();
        $hasUnmatchedIncluded = collect($rows)->contains(fn ($r) => $r['included'] && ! $r['matched']);

        return [
            'analysis' => $analysis,
            'vendor' => $s['vendor'] ?? 'Bompengeoperatør',
            'invoiceNumber' => $s['invoice_number'] ?? null,
            'invoiceTotal' => ! empty($s['total_ore']) ? (new Money((int) $s['total_ore']))->format() : null,
            'rows' => $rows,
            'rowCount' => count($rows),
            'matchedCount' => $matchedCount,
            'includedTotal' => new Money((int) $includedTotal),
            'groups' => collect($byProperty)->map(fn ($ore, $name) => ['property' => $name, 'amount' => (new Money((int) $ore))->format()])->values(),
            'hasUnmatchedIncluded' => $hasUnmatchedIncluded,
            'properties' => Property::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <a href="{{ route('intake') }}" class="text-[13px] text-muted transition-colors hover:text-ink">← Innboks</a>
    <h1 class="mt-2 text-3xl font-bold tracking-tight md:text-[34px]">Bompenger mot kjørebok</h1>
    <p class="mb-7 mt-1 text-[15px] text-muted">
        {{ $vendor }}@if ($invoiceNumber) · faktura {{ $invoiceNumber }}@endif@if ($invoiceTotal) · totalt {{ $invoiceTotal }}@endif.
        Kun passeringer på dager du har ført kjøring teller som fradrag.
    </p>

    @if ($booked)
        <x-card class="flex flex-col gap-4 border-positive-line bg-positive-soft px-6 py-6 md:flex-row md:items-center md:justify-between md:px-8 md:py-7">
            <div>
                <div class="text-lg font-semibold text-positive-strong">Bompenger bokført ✓</div>
                <div class="mt-1.5 text-sm text-[#4d7a5e]">
                    @if ($bookedSummary)
                        {{ $bookedSummary['total'] }} · {{ $bookedSummary['count'] }} passeringer
                        @foreach ($bookedSummary['groups'] as $g) · {{ $g['property'] }} {{ $g['amount'] }} @endforeach
                    @else
                        Allerede bokført tidligere.
                    @endif
                </div>
            </div>
            <a href="{{ route('intake') }}" class="shrink-0 rounded-[10px] bg-positive-strong px-[18px] py-2.5 text-sm font-semibold text-white">Til innboks</a>
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.7fr_1fr] lg:gap-8">
            {{-- Passings --}}
            <x-card class="overflow-hidden p-0">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <div class="text-[13px] uppercase tracking-[0.08em] text-faint">{{ $rowCount }} passeringer · {{ $matchedCount }} matcher kjørebok</div>
                    <button type="button" wire:click="selectMatchedOnly" class="text-[13px] font-semibold text-terra transition-opacity hover:opacity-80">Velg kun matchede</button>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($rows as $row)
                        <button type="button" wire:click="toggle({{ $row['index'] }})"
                            @class([
                                'flex w-full items-center gap-3.5 px-5 py-3 text-left transition-colors',
                                'bg-terra-soft' => $row['included'],
                                'hover:bg-surface' => ! $row['included'],
                            ])>
                            {{-- checkbox --}}
                            <span @class([
                                'flex size-5 shrink-0 items-center justify-center rounded-md border',
                                'border-terra bg-terra text-white' => $row['included'],
                                'border-line-strong bg-surface' => ! $row['included'],
                            ])>
                                @if ($row['included'])
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-[14.5px] font-medium">{{ $row['station'] ?: '—' }}</span>
                                    <span class="text-xs text-faint">{{ $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d.m.Y') : '—' }}@if ($row['time']) · {{ $row['time'] }}@endif</span>
                                </div>
                                @if ($row['matched'])
                                    <div class="mt-0.5 text-[12px] text-positive-strong">✓ {{ $row['trip_purpose'] }} — {{ $row['property_name'] ?? 'kjøring uten eiendom' }}</div>
                                @else
                                    <div class="mt-0.5 text-[12px] text-faint">Ingen kjøring ført denne dagen</div>
                                @endif
                            </div>

                            <div class="shrink-0 text-right text-[14.5px] font-semibold {{ $row['amount_ore'] === 0 ? 'text-faint' : '' }}">
                                {{ (new \App\Support\Money($row['amount_ore']))->format() }}
                            </div>
                        </button>
                    @endforeach
                </div>

                @if ($rowCount === 0)
                    <div class="px-5 py-10 text-center text-sm text-muted">Fant ingen passeringer i bilaget.</div>
                @endif
            </x-card>

            {{-- Booking summary --}}
            <div>
                <x-card class="p-6 md:p-7">
                    <div class="text-[13px] uppercase tracking-[0.08em] text-faint">Til fradrag</div>
                    <div class="mb-1 mt-3 text-[40px] font-bold tracking-tight">{{ $includedTotal->format() }}</div>
                    <div class="mb-5 text-sm text-muted">Bompenger · Drift</div>

                    @forelse ($groups as $g)
                        <div class="flex justify-between border-t border-line py-2.5 text-sm">
                            <span class="text-muted">{{ $g['property'] }}</span>
                            <span class="font-medium">{{ $g['amount'] }}</span>
                        </div>
                    @empty
                        <p class="border-t border-line py-3 text-[13px] text-faint">Huk av passeringene som hører til utleiekjøring.</p>
                    @endforelse

                    @if ($hasUnmatchedIncluded)
                        <label class="mt-4 block text-[12.5px] font-semibold text-ink-soft">Eiendom for passeringer uten ført kjøring</label>
                        <select wire:model.live="fallbackPropertyId" class="mt-1.5 w-full appearance-none rounded-[10px] border border-line-strong bg-surface px-3 py-2.5 text-sm">
                            @foreach ($properties as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <button type="button" wire:click="book" wire:loading.attr="disabled" wire:target="book"
                        @disabled($includedTotal->ore === 0)
                        class="mt-5 w-full rounded-[11px] bg-terra py-3.5 text-[15px] font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-40">
                        Bokfør {{ $includedTotal->format() }}
                    </button>
                    <p class="mt-3 text-[12.5px] leading-relaxed text-faint">
                        Resten regnes som privat kjøring og bokføres ikke. Hele bilaget arkiveres som dokumentasjon.
                    </p>
                </x-card>
            </div>
        </div>
    @endif
</div>
