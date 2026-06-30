<?php

use App\Actions\AnalyzeDocument;
use App\Enums\AnalysisStatus;
use App\Enums\ExpenseType;
use App\Models\Document;
use App\Models\DocumentAnalysis;
use App\Models\Expense;
use App\Models\Property;
use App\Services\PropertyMatcher;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component
{
    use WithFileUploads;

    public ?int $property_id = null;
    public string $gjelder = 'felles';   // 'felles' or a unit id (string), for bygårder
    public string $type = '';
    public string $category = '';
    public string $amount = '';
    public string $date = '';
    public string $description = '';
    public $receipt = null;

    // AI intake state
    public ?int $documentId = null;
    public ?int $analysisId = null;
    public ?string $vendor = null;
    public ?string $vendorOrgnr = null;
    public ?array $suggestion = null;
    public ?string $aiError = null;
    public ?string $propertyMatchedFrom = null;   // hint text when the eiendom was auto-selected

    public bool $submitted = false;
    public array $saved = [];

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->property_id = Property::query()->withCount('units')
            ->orderByDesc('units_count')->orderBy('name')->first()?->id;

        // Arriving from the Innboks: open with this draft analysis pre-filled.
        if ($analysisId = request()->integer('analysis')) {
            $analysis = DocumentAnalysis::find($analysisId);
            if ($analysis && in_array($analysis->status, [AnalysisStatus::Draft, AnalysisStatus::Failed], true)) {
                $this->documentId = $analysis->document_id;
                if ($analysis->status === AnalysisStatus::Draft && $analysis->suggested) {
                    $this->analysisId = $analysis->id;
                    $this->prefillFrom($analysis);
                }
                $this->receipt = 'existing';   // string marker → "Bilag lagt ved" UI
            }
        }
    }

    public function updatedPropertyId(): void
    {
        $this->gjelder = 'felles';
        $this->propertyMatchedFrom = null;   // user took over the eiendom choice
    }

    public function selectType(string $value): void
    {
        $this->type = $value;
    }

    public function selectCategory(string $value): void
    {
        $this->category = $this->category === $value ? '' : $value;
    }

    public function removeReceipt(): void
    {
        $this->reset(['receipt', 'documentId', 'analysisId', 'vendor', 'vendorOrgnr', 'suggestion', 'aiError']);
    }

    private function storeDocument(): Document
    {
        $hash = hash_file('sha256', $this->receipt->getRealPath());
        $path = $this->receipt->store('documents', 'local');

        $document = Document::create([
            'type' => 'receipt',
            'original_filename' => $this->receipt->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $this->receipt->getMimeType(),
            'size_bytes' => $this->receipt->getSize(),
            'hash' => $hash,
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
        ]);

        $this->documentId = $document->id;

        return $document;
    }

    public function analyzeWithAi(AnalyzeDocument $analyze): void
    {
        // When the bilag is already stored (e.g. re-analyzing one from the
        // Innboks), there is no fresh file upload to validate.
        if (! $this->documentId) {
            $this->validate([
                'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ], [
                'receipt.required' => 'Last opp et bilag først.',
                'receipt.mimes' => 'Bilaget må være PDF, JPG eller PNG.',
            ]);
        }

        $this->aiError = null;
        $document = $this->documentId ? Document::find($this->documentId) : $this->storeDocument();

        $analysis = $analyze($document);

        if ($analysis->status === AnalysisStatus::Failed) {
            $this->aiError = $analysis->error ?: 'AI-analysen feilet.';

            return;
        }

        $this->analysisId = $analysis->id;
        $this->prefillFrom($analysis);
    }

    private function prefillFrom(DocumentAnalysis $analysis): void
    {
        $s = $analysis->suggested ?? [];

        $this->type = $s['suggested_type'] ?? $this->type;
        $this->category = $s['suggested_category'] ?? $this->category;
        $this->date = $s['date'] ?? $this->date;
        $this->vendor = $s['vendor'] ?? null;
        $this->vendorOrgnr = $s['vendor_orgnr'] ?? null;

        // Auto-select the eiendom the bilag concerns (e.g. "Blekenberg 36"),
        // and the specific unit if a bruksenhet code was on the bilag.
        if (! empty($s['property_hint'])) {
            $match = app(PropertyMatcher::class)->match($s['property_hint']);
            if ($match['property']) {
                $this->property_id = $match['property']->id;
                $this->gjelder = $match['unit'] ? (string) $match['unit']->id : 'felles';
                $this->propertyMatchedFrom = $s['property_hint'];
            }
        }

        if (! empty($s['total_ore'])) {
            $this->amount = (new Money((int) $s['total_ore']))->format(symbol: false, decimals: true);
        }
        if (empty($this->description) && ! empty($s['vendor'])) {
            $this->description = $s['vendor'];
        }

        $total = ! empty($s['total_ore']) ? (new Money((int) $s['total_ore']))->format() : null;

        $this->suggestion = [
            'confidence' => (int) round(((float) ($s['confidence'] ?? 0)) * 100),
            'rationale' => $s['rationale'] ?? null,
            'vendor' => $s['vendor'] ?? null,
            'total' => $total,
            'date' => $s['date'] ? Carbon::parse($s['date'])->format('d.m.Y') : null,
        ];
    }

    public function save(): void
    {
        $rules = [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'type' => ['required', Rule::enum(ExpenseType::class)],
            'amount' => ['required', 'string'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
        ];
        if (! $this->documentId) {
            $rules['receipt'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        }

        $this->validate($rules, [
            'property_id.required' => 'Velg en eiendom.',
            'type.required' => 'Velg en kostnadstype.',
            'amount.required' => 'Fyll inn et beløp.',
            'date.required' => 'Velg en dato.',
        ]);

        $money = Money::fromKronerString($this->amount);
        if (! $money->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Beløpet må være større enn 0.']);
        }

        $property = Property::with('units')->findOrFail($this->property_id);
        $unitId = $this->resolveUnitId($property);

        $documentId = $this->documentId;
        if (! $documentId && $this->receipt) {
            $documentId = $this->storeDocument()->id;
        }

        $expense = Expense::create([
            'property_id' => $property->id,
            'unit_id' => $unitId,
            'document_id' => $documentId,
            'document_analysis_id' => $this->analysisId,
            'date' => $this->date,
            'amount_ore' => $money,
            'vendor' => $this->vendor,
            'vendor_orgnr' => $this->vendorOrgnr,
            'category' => $this->category ?: null,
            'type' => $this->type,
            'description' => $this->description ?: null,
            'created_by' => Auth::id(),
        ]);

        if ($this->analysisId) {
            DocumentAnalysis::whereKey($this->analysisId)->update([
                'status' => AnalysisStatus::Confirmed->value,
                'confirmed_expense_id' => $expense->id,
                'confirmed_at' => now(),
            ]);
        }

        $this->saved = [
            'amount' => $money->format(),
            'type' => ExpenseType::from($this->type)->label(),
            'gjelder' => $this->gjelderText($property, $unitId),
            'receipt' => (bool) $documentId,
        ];
        $this->submitted = true;
    }

    /** Resolve the chosen scope to a unit_id: null = felleskostnad (whole building). */
    private function resolveUnitId(Property $property): ?int
    {
        if (! $property->isBuilding()) {
            return $property->units->first()?->id;
        }

        if ($this->gjelder === 'felles') {
            return null;
        }

        return $property->units->contains('id', (int) $this->gjelder) ? (int) $this->gjelder : null;
    }

    private function gjelderText(Property $property, ?int $unitId): string
    {
        if (! $property->isBuilding()) {
            return $property->name;
        }

        return $unitId
            ? ($property->units->firstWhere('id', $unitId)?->name ?? $property->name)
            : 'Hele bygården (felles)';
    }

    public function resetForm(): void
    {
        $this->reset(['type', 'category', 'amount', 'description', 'receipt', 'submitted', 'saved',
            'documentId', 'analysisId', 'vendor', 'vendorOrgnr', 'suggestion', 'aiError', 'gjelder', 'propertyMatchedFrom']);
        $this->date = now()->format('Y-m-d');
    }

    public function with(): array
    {
        $properties = Property::withCount('units')->with('units')->orderByDesc('units_count')->orderBy('name')->get();
        $selected = $properties->firstWhere('id', $this->property_id);
        $isBuilding = $selected?->isBuilding() ?? false;

        $gjelderText = $selected
            ? ($isBuilding
                ? ($this->gjelder === 'felles' ? 'Hele bygården (felles)' : ($selected->units->firstWhere('id', (int) $this->gjelder)?->name ?? '—'))
                : $selected->name)
            : '—';

        return [
            'properties' => $properties,
            'isBuilding' => $isBuilding,
            'unitOptions' => $isBuilding ? $selected->units : collect(),
            'categories' => config('forvalter.categories'),
            'types' => ExpenseType::cases(),
            'summaryAmount' => $this->amount !== '' ? Money::fromKronerString($this->amount)->format() : 'kr 0',
            'gjelderText' => $gjelderText,
            'propertyName' => $selected?->name ?? '—',
            'summaryDate' => $this->date ? Carbon::parse($this->date)->format('d.m.Y') : '—',
            'document' => $this->documentId ? Document::find($this->documentId) : null,
        ];
    }
};
?>

<div x-data="{ preview: null }">
    <h1 class="text-[34px] font-bold tracking-tight">Registrer utgift</h1>
    <p class="mb-8 mt-1 text-[15px] text-muted">Legg inn et bilag og knytt det til en eiendom.</p>

    @if ($submitted)
        <x-card class="flex flex-col gap-4 border-positive-line bg-positive-soft px-6 py-6 md:flex-row md:items-center md:justify-between md:px-8 md:py-7">
            <div>
                <div class="text-lg font-semibold text-positive-strong">Utgiften er registrert ✓</div>
                <div class="mt-1.5 text-sm text-[#4d7a5e]">{{ $saved['amount'] }} · {{ $saved['type'] }} · {{ $saved['gjelder'] }}</div>
            </div>
            <div class="flex gap-2.5">
                <button wire:click="resetForm" type="button"
                    class="rounded-[10px] border border-positive-line bg-surface px-[18px] py-2.5 text-sm font-semibold text-positive-strong">Registrer ny</button>
                <a href="{{ route('dashboard') }}"
                    class="rounded-[10px] bg-positive-strong px-[18px] py-2.5 text-sm font-semibold text-white">Til oversikt</a>
            </div>
        </x-card>
    @else
        <form wire:submit="save" class="grid grid-cols-1 gap-6 md:grid-cols-[1.5fr_1fr] md:gap-8">
            <x-card class="p-6 md:p-8">
                @if ($suggestion)
                    <div class="mb-6 rounded-xl border border-terra/20 bg-terra-soft p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-terra">✨ Forhåndsutfylt fra bilag</div>
                            <span class="rounded-full bg-surface px-2.5 py-1 text-[11.5px] font-semibold text-muted">{{ $suggestion['confidence'] }} % sikkerhet</span>
                        </div>
                        @if ($suggestion['rationale'])
                            <p class="mt-1.5 text-[13px] leading-relaxed text-ink-soft">{{ $suggestion['rationale'] }}</p>
                        @endif
                        <div class="mt-2 text-xs text-faint">
                            {{ collect([$suggestion['vendor'], $suggestion['total'], $suggestion['date']])->filter()->implode(' · ') }}
                            — kontroller feltene og bekreft.
                        </div>
                    </div>
                @endif

                {{-- Eiendom --}}
                <label class="mb-2 block text-[13px] font-semibold text-ink-soft">Eiendom</label>
                <select wire:model.live="property_id"
                    class="w-full appearance-none rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px]">
                    @foreach ($properties as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('property_id') <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p> @enderror
                @if ($propertyMatchedFrom)
                    <p class="mt-1.5 text-[12.5px] text-terra">✨ Valgt automatisk fra bilaget («{{ $propertyMatchedFrom }}») — endre hvis det er feil.</p>
                @endif

                {{-- Gjelder (only for bygårder) --}}
                @if ($isBuilding)
                    <label class="mt-5 block text-[13px] font-semibold text-ink-soft">Gjelder</label>
                    <select wire:model.live="gjelder"
                        class="w-full appearance-none rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px]">
                        <option value="felles">Hele bygården (felles)</option>
                        @foreach ($unitOptions as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}@if ($u->code) ({{ $u->code }})@endif</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-[12.5px] text-faint">Velg «Hele bygården (felles)» for felleskostnader som holdes på byggnivå.</p>
                @endif

                {{-- Kostnadstype --}}
                <div class="mt-6 flex items-baseline justify-between">
                    <label class="text-[13px] font-semibold text-ink-soft">Kostnadstype <span class="text-terra">*</span></label>
                    <span class="text-xs text-faint">Avgjør skattemessig behandling</span>
                </div>
                <div class="mt-2.5 grid grid-cols-2 gap-2.5">
                    @foreach ($types as $t)
                        <button type="button" wire:click="selectType('{{ $t->value }}')" @class([
                            'rounded-xl border p-3 text-left transition-colors',
                            'border-terra bg-terra-soft' => $type === $t->value,
                            'border-line-strong bg-surface hover:border-faint' => $type !== $t->value,
                        ])>
                            <div class="text-sm font-semibold {{ $type === $t->value ? 'text-terra' : 'text-ink' }}">{{ $t->label() }}</div>
                            <div class="mt-0.5 text-[11.5px] text-faint">{{ $t->taxTreatment() }}</div>
                        </button>
                    @endforeach
                </div>
                @error('type') <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p> @enderror

                {{-- Kategori --}}
                <label class="mt-6 block text-[13px] font-semibold text-ink-soft">Kategori</label>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    @foreach ($categories as $cat)
                        <button type="button" wire:click="selectCategory('{{ $cat }}')" @class([
                            'rounded-full border px-3.5 py-2 text-[13.5px] font-medium transition-colors',
                            'border-terra bg-terra text-white' => $category === $cat,
                            'border-line-strong bg-surface text-ink-soft hover:border-faint' => $category !== $cat,
                        ])>{{ $cat }}</button>
                    @endforeach
                </div>

                {{-- Beløp + Dato --}}
                <div class="mt-6 grid grid-cols-2 gap-[18px]">
                    <div>
                        <label class="mb-2 block text-[13px] font-semibold text-ink-soft">Beløp (kr)</label>
                        <input wire:model.live.debounce.400ms="amount" inputmode="decimal" placeholder="0"
                            class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px] outline-none focus:border-terra">
                        @error('amount') <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-[13px] font-semibold text-ink-soft">Dato</label>
                        <input wire:model.live="date" type="date"
                            class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px] outline-none focus:border-terra">
                        @error('date') <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Beskrivelse --}}
                <label class="mt-6 block text-[13px] font-semibold text-ink-soft">Beskrivelse</label>
                <input wire:model="description" placeholder="F.eks. utskifting av blandebatteri"
                    class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px] outline-none focus:border-terra">

                {{-- Kvittering + AI --}}
                <label class="mt-6 block text-[13px] font-semibold text-ink-soft">Kvittering (bilag)</label>
                @if (! $receipt)
                    <div wire:loading.remove wire:target="receipt" class="mt-2 flex flex-col gap-2.5 sm:flex-row">
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-line-strong bg-panel px-4 py-4 text-sm font-semibold text-ink-soft transition-colors hover:border-faint">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3.5"/></svg>
                            Ta bilde
                            <input type="file" wire:model="receipt" accept="image/*" capture="environment" class="hidden">
                        </label>
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-line-strong bg-panel px-4 py-4 text-sm font-semibold text-ink-soft transition-colors hover:border-faint">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15V8a2 2 0 0 0-2-2h-7l-2-2H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2z"/></svg>
                            Velg bilde eller PDF
                            <input type="file" wire:model="receipt" accept="image/*,application/pdf" class="hidden">
                        </label>
                    </div>
                    <div wire:loading.flex wire:target="receipt" class="mt-2 items-center justify-center rounded-xl border border-line bg-panel px-4 py-4 text-sm font-semibold text-muted">Laster opp bilde …</div>
                @else
                    <div class="mt-2 rounded-xl border border-line bg-panel p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-[14px] font-medium">{{ is_string($receipt) ? ($document?->original_filename ?? 'Bilag lagt ved') : $receipt->getClientOriginalName() }}</div>
                            <button type="button" wire:click="removeReceipt" class="text-[13px] text-faint hover:text-terra">Fjern</button>
                        </div>
                        @if ($document)
                            <x-bilag-preview :document="$document" height="h-48" class="mt-3" />
                        @endif
                        @if (! $analysisId)
                            <button type="button" wire:click="analyzeWithAi" wire:loading.attr="disabled" wire:target="analyzeWithAi"
                                class="mt-3 w-full rounded-[10px] bg-terra py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-60">
                                <span wire:loading.remove wire:target="analyzeWithAi">✨ Analyser bilag med AI</span>
                                <span wire:loading wire:target="analyzeWithAi">Analyserer bilag …</span>
                            </button>
                        @else
                            <div class="mt-2 text-[13px] font-medium text-positive">Analysert ✓ — feltene er forhåndsutfylt.</div>
                        @endif
                        @if ($aiError)
                            <p class="mt-2 text-[13px] text-terra">{{ $aiError }} Du kan fylle inn manuelt.</p>
                        @endif
                    </div>
                @endif
                @error('receipt') <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p> @enderror
            </x-card>

            {{-- Oppsummering --}}
            <div>
                <x-card class="p-7">
                    <div class="text-[13px] uppercase tracking-[0.08em] text-faint">Oppsummering</div>
                    <div class="mb-1 mt-3.5 text-[44px] font-bold tracking-tight">{{ $summaryAmount }}</div>
                    <div class="mb-5 text-sm text-muted">{{ $type ? \App\Enums\ExpenseType::from($type)->label() : 'Ingen kostnadstype valgt' }}</div>

                    <div class="flex justify-between border-t border-line py-3 text-sm">
                        <span class="text-muted">Eiendom</span><span class="font-medium">{{ $propertyName }}</span>
                    </div>
                    <div class="flex justify-between border-t border-line py-3 text-sm">
                        <span class="text-muted">Gjelder</span><span class="font-medium">{{ $gjelderText }}</span>
                    </div>
                    <div class="flex justify-between border-t border-line py-3 text-sm">
                        <span class="text-muted">Dato</span><span class="font-medium">{{ $summaryDate }}</span>
                    </div>
                    <div class="flex justify-between border-t border-line py-3 text-sm">
                        <span class="text-muted">Kvittering</span>
                        <span class="font-medium {{ ($receipt || $documentId) ? 'text-positive' : 'text-terra' }}">{{ ($receipt || $documentId) ? 'Lagt ved' : 'Mangler' }}</span>
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="mt-5 w-full rounded-[11px] bg-terra py-3.5 text-[15px] font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-60">
                        Registrer utgift
                    </button>
                </x-card>
                <p class="mt-4 px-1 text-[13px] leading-relaxed text-faint">
                    Tips: Felleskostnader (forsikring, kommunale avgifter, lån) føres på «Hele bygården».
                </p>
            </div>
        </form>
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
