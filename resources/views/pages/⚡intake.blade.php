<?php

use App\Enums\AnalysisStatus;
use App\Enums\ExpenseType;
use App\Jobs\AnalyzeDocumentJob;
use App\Models\DocumentAnalysis;
use App\Services\PropertyMatcher;
use App\Services\Toll\TollMatcher;
use App\Support\Money;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    /** Discard a draft — kept as Forkastet for history, never hard-deleted. */
    public function discard(int $id): void
    {
        DocumentAnalysis::whereKey($id)
            ->whereIn('status', [AnalysisStatus::Draft->value, AnalysisStatus::Failed->value])
            ->update(['status' => AnalysisStatus::Discarded->value]);
    }

    /** Re-run a failed analysis. Back to Pending so the poll picks it up again. */
    public function retry(int $id): void
    {
        $analysis = DocumentAnalysis::where('status', AnalysisStatus::Failed->value)->find($id);
        if (! $analysis) {
            return;
        }

        $analysis->update(['status' => AnalysisStatus::Pending->value, 'error' => null]);
        AnalyzeDocumentJob::dispatch($analysis->id)->afterResponse();
    }

    /** A bilag was just dropped somewhere — re-render so it shows + polling resumes. */
    #[On('bilag-mottatt')]
    public function refreshInbox(): void
    {
        // no-op; the round-trip re-renders with() and restarts wire:poll.
    }

    public function with(): array
    {
        $pending = DocumentAnalysis::with('document')
            ->where('status', AnalysisStatus::Pending->value)
            ->latest()->get();

        $drafts = DocumentAnalysis::with('document')
            ->where('status', AnalysisStatus::Draft->value)
            ->latest()->get();

        $failed = DocumentAnalysis::with('document')
            ->where('status', AnalysisStatus::Failed->value)
            ->latest()->get();

        $confirmed = DocumentAnalysis::with(['document', 'confirmedExpense.property'])
            ->where('status', AnalysisStatus::Confirmed->value)
            ->latest('confirmed_at')->take(6)->get();

        // Toll drafts get a kjørebok-match preview instead of the normal card.
        $matcher = app(TollMatcher::class);
        $propertyMatcher = app(PropertyMatcher::class);
        $tollMeta = [];
        $propertyMatch = [];
        foreach ($drafts as $a) {
            $passings = $a->suggested['toll_passings'] ?? [];
            $isToll = ($a->suggested['document_type'] ?? null) === 'bompenger' || ! empty($passings);
            if ($isToll) {
                $matched = $matcher->match($passings);
                $tollMeta[$a->id] = [
                    'count' => count($passings),
                    'matched_count' => collect($matched)->where('matched', true)->count(),
                    'matched_total' => new Money((int) collect($matched)->where('matched', true)->sum('amount_ore')),
                ];

                continue;
            }

            if (! empty($a->suggested['property_hint'])) {
                $propertyMatch[$a->id] = $propertyMatcher->match($a->suggested['property_hint'])['property']?->name;
            }
        }

        return [
            'pending' => $pending,
            'drafts' => $drafts,
            'failed' => $failed,
            'confirmed' => $confirmed,
            'tollMeta' => $tollMeta,
            'propertyMatch' => $propertyMatch,
            'hasPending' => $pending->isNotEmpty(),
        ];
    }
};
?>

<div x-data="{ preview: null }" @if ($hasPending) wire:poll.3s @endif>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight md:text-[34px]">Innboks</h1>
            <p class="mb-8 mt-1 text-[15px] text-muted">Slipp et bilag hvor som helst på siden — det leses og forhåndsutfylles automatisk.</p>
        </div>
        @if ($hasPending)
            <span class="mt-1.5 inline-flex shrink-0 items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-[12.5px] font-medium text-muted">
                <span class="size-1.5 animate-pulse rounded-full bg-terra"></span>
                {{ $pending->count() }} behandles
            </span>
        @endif
    </div>

    {{-- Behandles — being read by the AI right now --}}
    @foreach ($pending as $a)
        <x-card class="mb-3 flex items-center gap-4 p-5">
            <svg class="size-5 shrink-0 animate-spin text-terra" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.2"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            <div class="min-w-0">
                <div class="truncate text-[15px] font-medium">{{ $a->document?->original_filename ?? 'Bilag' }}</div>
                <div class="text-[13px] text-faint">Leser bilaget …</div>
            </div>
        </x-card>
    @endforeach

    {{-- Til gjennomgang — drafts the AI has prepared --}}
    @if ($drafts->isNotEmpty())
        <div class="mb-3 mt-7 text-[13px] uppercase tracking-[0.08em] text-faint">Til gjennomgang</div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($drafts as $a)
                @php
                    $s = $a->suggested ?? [];
                    $type = ! empty($s['suggested_type']) ? ExpenseType::tryFrom($s['suggested_type']) : null;
                    $total = ! empty($s['total_ore']) ? (new Money((int) $s['total_ore']))->format() : null;
                    $date = ! empty($s['date']) ? Carbon::parse($s['date'])->format('d.m.Y') : null;
                    $confidence = (int) round(((float) ($s['confidence'] ?? $a->confidence ?? 0)) * 100);
                    $toll = $tollMeta[$a->id] ?? null;
                @endphp

                @if ($toll)
                    {{-- Bompenger draft → kjørebok matching --}}
                    <x-card class="flex flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-ink/5 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-muted">Bompenger</span>
                                    <span class="text-[17px] font-semibold leading-tight">{{ $s['vendor'] ?? 'Bompengeoperatør' }}</span>
                                </div>
                                <div class="mt-0.5 truncate text-[12.5px] text-faint">{{ $a->document?->original_filename }}</div>
                            </div>
                            @if ($total)
                                <span class="shrink-0 text-right text-[12.5px] text-faint">faktura<br><span class="text-[14px] font-semibold text-ink">{{ $total }}</span></span>
                            @endif
                        </div>

                        @if ($a->document)
                            <x-bilag-preview :document="$a->document" class="mt-4" />
                        @endif

                        <div class="mt-4 rounded-xl border border-terra/15 bg-terra-soft p-4">
                            <div class="flex items-end justify-between">
                                <div>
                                    <div class="text-[11.5px] uppercase tracking-[0.06em] text-faint">Matcher kjørebok</div>
                                    <div class="text-2xl font-bold tracking-tight text-terra">{{ $toll['matched_total']->format() }}</div>
                                </div>
                                <div class="pb-1 text-right text-[12.5px] text-muted">{{ $toll['matched_count'] }} av {{ $toll['count'] }} passeringer</div>
                            </div>
                        </div>

                        <p class="mt-3 text-[13px] leading-relaxed text-ink-soft">✨ Bare passeringer på dager med ført kjøring teller som fradrag — kontrollér og bokfør.</p>

                        <div class="mt-5 flex items-center gap-2.5 border-t border-line pt-4">
                            <a href="{{ route('intake.toll', ['analysis' => $a->id]) }}"
                                class="flex-1 rounded-[10px] bg-terra px-4 py-2.5 text-center text-sm font-semibold text-white transition-opacity hover:opacity-90">
                                Match mot kjørebok
                            </a>
                            <button type="button" wire:click="discard({{ $a->id }})" wire:confirm="Forkaste dette bilaget?"
                                class="rounded-[10px] border border-line-strong px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:border-faint hover:text-ink">
                                Forkast
                            </button>
                        </div>
                    </x-card>
                    @continue
                @endif

                <x-card class="flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[17px] font-semibold leading-tight">{{ $s['vendor'] ?? 'Ukjent leverandør' }}</div>
                            <div class="mt-0.5 truncate text-[12.5px] text-faint">{{ $a->document?->original_filename }}</div>
                        </div>
                        @if ($confidence > 0)
                            <span class="shrink-0 rounded-full bg-terra-soft px-2.5 py-1 text-[11.5px] font-semibold text-terra">{{ $confidence }} %</span>
                        @endif
                    </div>

                    @if ($a->document)
                        <x-bilag-preview :document="$a->document" class="mt-4" />
                    @endif

                    <div class="mt-4 flex items-end gap-5">
                        <div>
                            <div class="text-[11.5px] uppercase tracking-[0.06em] text-faint">Beløp</div>
                            <div class="text-2xl font-bold tracking-tight">{{ $total ?? '—' }}</div>
                        </div>
                        <div class="pb-1">
                            <div class="text-[11.5px] uppercase tracking-[0.06em] text-faint">Dato</div>
                            <div class="text-[15px] font-medium">{{ $date ?? '—' }}</div>
                        </div>
                    </div>

                    @if ($type)
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-teal-line bg-teal-soft px-3 py-1 text-[12.5px] font-semibold text-teal">{{ $type->label() }}</span>
                            @if (! empty($s['suggested_category']))
                                <span class="rounded-full border border-line-strong px-3 py-1 text-[12.5px] text-ink-soft">{{ $s['suggested_category'] }}</span>
                            @endif
                        </div>
                    @endif

                    @php($matchedProperty = $propertyMatch[$a->id] ?? null)
                    @if ($matchedProperty || ! empty($s['property_hint']))
                        <div class="mt-3 flex items-center gap-1.5 text-[12.5px]">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0 text-faint"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.3"/></svg>
                            @if ($matchedProperty)
                                <span class="text-ink-soft">Eiendom: <span class="font-semibold">{{ $matchedProperty }}</span></span>
                            @else
                                <span class="text-faint">Eiendom «{{ $s['property_hint'] }}» — ingen treff, velg manuelt</span>
                            @endif
                        </div>
                    @endif

                    @if (! empty($s['rationale']))
                        <p class="mt-3 text-[13px] leading-relaxed text-ink-soft">✨ {{ $s['rationale'] }}</p>
                    @endif

                    <div class="mt-5 flex items-center gap-2.5 border-t border-line pt-4">
                        <a href="{{ route('expenses.create', ['analysis' => $a->id]) }}"
                            class="flex-1 rounded-[10px] bg-terra px-4 py-2.5 text-center text-sm font-semibold text-white transition-opacity hover:opacity-90">
                            Gjennomgå og bokfør
                        </a>
                        <button type="button" wire:click="discard({{ $a->id }})" wire:confirm="Forkaste dette bilaget?"
                            class="rounded-[10px] border border-line-strong px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:border-faint hover:text-ink">
                            Forkast
                        </button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    {{-- Feilet — analysis could not be produced --}}
    @if ($failed->isNotEmpty())
        <div class="mb-3 mt-7 text-[13px] uppercase tracking-[0.08em] text-faint">Feilet</div>
        @foreach ($failed as $a)
            <x-card class="mb-3 flex flex-col gap-3 border-negative/30 bg-negative-soft p-5 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-center gap-3.5">
                    @if ($a->document)
                        <x-bilag-preview :document="$a->document" height="h-16" class="!w-16 shrink-0" />
                    @endif
                    <div class="min-w-0">
                        <div class="truncate text-[15px] font-medium">{{ $a->document?->original_filename ?? 'Bilag' }}</div>
                        <div class="mt-0.5 text-[13px] text-terra">{{ $a->error ?: 'AI-analysen feilet.' }}</div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2.5">
                    <a href="{{ route('expenses.create', ['analysis' => $a->id]) }}"
                        class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:border-faint">
                        Fyll inn manuelt
                    </a>
                    <button type="button" wire:click="retry({{ $a->id }})"
                        class="rounded-[10px] bg-terra px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90">
                        Prøv igjen
                    </button>
                </div>
            </x-card>
        @endforeach
    @endif

    {{-- Empty state --}}
    @if ($pending->isEmpty() && $drafts->isEmpty() && $failed->isEmpty())
        <x-card class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
            <svg class="size-9 text-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 14h4l2 3h4l2-3h4M4 14l2.5-8h11L20 14M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>
            </svg>
            <div class="text-[15px] font-semibold">Innboksen er tom</div>
            <p class="max-w-sm text-[13.5px] text-muted">Dra en PDF eller et bilde av et bilag inn på siden — hvor som helst — så leser AI-en det og legger et utkast her.</p>
        </x-card>
    @endif

    {{-- Nylig bokført --}}
    @if ($confirmed->isNotEmpty())
        <div class="mb-1 mt-9 text-[13px] uppercase tracking-[0.08em] text-faint">Nylig bokført</div>
        @foreach ($confirmed as $a)
            <div class="flex items-center justify-between border-b border-line py-3.5">
                <div class="min-w-0">
                    <div class="truncate text-[14.5px] font-medium">{{ $a->confirmedExpense?->vendor ?? $a->document?->original_filename ?? 'Bilag' }}</div>
                    <div class="mt-0.5 text-xs text-faint">
                        {{ $a->confirmedExpense?->property?->name ?? '—' }}
                        @if ($a->confirmed_at) · {{ $a->confirmed_at->format('d.m.Y') }} @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 text-right">
                    @if ($a->confirmedExpense)
                        <div class="text-[14.5px] font-semibold">{{ $a->confirmedExpense->amount_ore->format() }}</div>
                    @endif
                    <span class="rounded-full bg-positive-soft px-2.5 py-1 text-[11.5px] font-semibold text-positive-strong">Bokført ✓</span>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Bilag preview lightbox --}}
    <div x-show="preview" x-cloak x-transition.opacity
        @keydown.escape.window="preview = null"
        @click.self="preview = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4 backdrop-blur-[2px]">
        <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-canvas shadow-2xl" @click.stop>
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                <div class="truncate text-sm font-semibold" x-text="preview?.name"></div>
                <div class="flex shrink-0 items-center gap-4">
                    <a :href="preview?.url" target="_blank" class="text-[13px] font-semibold text-terra hover:opacity-80">Åpne i ny fane ↗</a>
                    <button type="button" @click="preview = null" class="text-xl leading-none text-faint transition-colors hover:text-ink" aria-label="Lukk">&times;</button>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-panel">
                <template x-if="preview && preview.img">
                    <img :src="preview.url" class="mx-auto block max-h-[78vh] w-auto" alt="Bilag">
                </template>
                <template x-if="preview && ! preview.img">
                    <iframe :src="preview.url" class="h-[78vh] w-full" title="Bilag"></iframe>
                </template>
            </div>
        </div>
    </div>
</div>
