<?php

use App\Enums\UnitStatus;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Money;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public Unit $unit;
    public int $year;

    /** Which modal is open: null | 'unit' | 'tenancy' | 'regulate' | 'payment'. */
    public ?string $modal = null;

    // Edit unit + current tenant/lease
    public string $f_name = '', $f_code = '', $f_unit_type = '', $f_area = '', $f_rooms = '';
    public string $f_tenant = '', $f_email = '', $f_phone = '', $f_rent = '', $f_deposit = '', $f_starts = '', $f_ends = '';

    // New tenancy
    public string $n_tenant = '', $n_email = '', $n_phone = '', $n_rent = '', $n_deposit = '', $n_starts = '';

    // Register payment
    public ?int $payIncomeId = null;
    public string $payDate = '';

    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load(['property', 'currentTenancy.tenant']);
        $this->year = (int) (Income::max('period_year') ?? now()->year);
    }

    private function reloadUnit(): void
    {
        $this->unit->refresh()->load(['property', 'currentTenancy.tenant']);
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    // ── Edit unit + current tenancy ──
    public function edit(): void
    {
        $t = $this->unit->currentTenancy;
        $this->f_name = $this->unit->name;
        $this->f_code = $this->unit->code ?? '';
        $this->f_unit_type = $this->unit->unit_type ?? '';
        $this->f_area = $this->unit->area_sqm !== null ? (string) $this->unit->area_sqm : '';
        $this->f_rooms = $this->unit->rooms !== null ? rtrim(rtrim(number_format((float) $this->unit->rooms, 1, '.', ''), '0'), '.') : '';
        $this->f_tenant = $t?->tenant?->name ?? '';
        $this->f_email = $t?->tenant?->email ?? '';
        $this->f_phone = $t?->tenant?->phone ?? '';
        $this->f_rent = $t?->monthly_rent_ore ? $t->monthly_rent_ore->format(symbol: false) : '';
        $this->f_deposit = $t?->deposit_ore ? $t->deposit_ore->format(symbol: false) : '';
        $this->f_starts = $t?->starts_on?->format('Y-m-d') ?? '';
        $this->f_ends = $t?->ends_on?->format('Y-m-d') ?? '';
        $this->resetValidation();
        $this->modal = 'unit';
    }

    public function saveUnit(): void
    {
        $this->validate([
            'f_name' => ['required', 'string', 'max:255'],
            'f_code' => ['nullable', 'string', 'max:20'],
            'f_unit_type' => ['nullable', 'string', 'max:50'],
            'f_area' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'f_rooms' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'f_email' => ['nullable', 'email', 'max:255'],
            'f_phone' => ['nullable', 'string', 'max:40'],
            'f_starts' => ['nullable', 'date'],
            'f_ends' => ['nullable', 'date'],
        ], ['f_name.required' => 'Enheten må ha et navn.', 'f_email.email' => 'Ugyldig e-postadresse.']);

        $this->unit->update([
            'name' => $this->f_name,
            'code' => $this->f_code ?: null,
            'unit_type' => $this->f_unit_type ?: null,
            'area_sqm' => $this->f_area !== '' ? (int) $this->f_area : null,
            'rooms' => $this->f_rooms !== '' ? (float) str_replace(',', '.', $this->f_rooms) : null,
        ]);

        if ($t = $this->unit->currentTenancy) {
            $t->update([
                'monthly_rent_ore' => $this->f_rent !== '' ? Money::fromKronerString($this->f_rent)->ore : $t->monthly_rent_ore->ore,
                'deposit_ore' => $this->f_deposit !== '' ? Money::fromKronerString($this->f_deposit)->ore : 0,
                'starts_on' => $this->f_starts ?: $t->starts_on,
                'ends_on' => $this->f_ends ?: null,
            ]);
            $t->tenant?->update([
                'name' => $this->f_tenant ?: $t->tenant->name,
                'email' => $this->f_email ?: null,
                'phone' => $this->f_phone ?: null,
            ]);
        }

        $this->reloadUnit();
        $this->modal = null;
    }

    // ── Rent ledger / payments ──
    public function generateRent(): void
    {
        $t = $this->unit->currentTenancy;
        if (! $t) {
            return;
        }

        $now = now();
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = Carbon::create($this->year, $m, 1);
            $active = $t->starts_on->lte($monthStart->copy()->endOfMonth())
                && ($t->ends_on === null || $t->ends_on->gte($monthStart));
            if (! $active || $monthStart->gt($now)) {
                continue;
            }
            $exists = Income::where('unit_id', $this->unit->id)
                ->where('period_year', $this->year)->where('period_month', $m)->exists();
            if ($exists) {
                continue;
            }
            Income::create([
                'unit_id' => $this->unit->id,
                'tenancy_id' => $t->id,
                'period_year' => $this->year,
                'period_month' => $m,
                'amount_ore' => $t->monthly_rent_ore->ore,
                'received_on' => null,
                'income_year' => $this->year,
            ]);
        }
    }

    public function openPayment(int $incomeId): void
    {
        $this->payIncomeId = $incomeId;
        $this->payDate = now()->format('Y-m-d');
        $this->resetValidation();
        $this->modal = 'payment';
    }

    public function confirmPayment(): void
    {
        $this->validate(['payDate' => ['required', 'date']]);
        Income::where('unit_id', $this->unit->id)->whereKey($this->payIncomeId)
            ->update(['received_on' => $this->payDate]);
        $this->modal = null;
    }

    public function markUnpaid(int $incomeId): void
    {
        Income::where('unit_id', $this->unit->id)->whereKey($incomeId)->update(['received_on' => null]);
    }

    // ── Tenancy lifecycle ──
    public function endTenancy(): void
    {
        if ($t = $this->unit->currentTenancy) {
            if ($t->ends_on === null || ! $t->ends_on->isPast()) {
                $t->update(['ends_on' => now()->toDateString()]);
            }
        }
        $this->reloadUnit();
    }

    public function openNewTenancy(): void
    {
        $this->reset(['n_tenant', 'n_email', 'n_phone', 'n_rent', 'n_deposit']);
        $this->n_starts = now()->format('Y-m-d');
        $this->resetValidation();
        $this->modal = 'tenancy';
    }

    public function saveTenancy(): void
    {
        $this->validate([
            'n_tenant' => ['required', 'string', 'max:255'],
            'n_email' => ['nullable', 'email', 'max:255'],
            'n_phone' => ['nullable', 'string', 'max:40'],
            'n_rent' => ['required', 'string'],
            'n_deposit' => ['nullable', 'string'],
            'n_starts' => ['required', 'date'],
        ], [
            'n_tenant.required' => 'Fyll inn leietakers navn.',
            'n_rent.required' => 'Fyll inn månedsleie.',
        ]);

        // Close out the current tenancy the day before the new one begins.
        $cur = $this->unit->currentTenancy;
        if ($cur && ($cur->ends_on === null || ! $cur->ends_on->isPast())) {
            $cur->update(['ends_on' => Carbon::parse($this->n_starts)->subDay()->toDateString()]);
        }

        $tenant = Tenant::create([
            'name' => $this->n_tenant,
            'email' => $this->n_email ?: null,
            'phone' => $this->n_phone ?: null,
        ]);
        Tenancy::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => $this->n_starts,
            'monthly_rent_ore' => Money::fromKronerString($this->n_rent)->ore,
            'deposit_ore' => $this->n_deposit !== '' ? Money::fromKronerString($this->n_deposit)->ore : 0,
        ]);

        $this->reloadUnit();
        $this->modal = null;
    }

    public function with(): array
    {
        $year = $this->year;
        $unit = $this->unit;
        $isBuilding = $unit->property->isBuilding();
        $tenancy = $unit->currentTenancy;
        $status = $unit->status();

        $expenseQuery = $isBuilding
            ? Expense::where('unit_id', $unit->id)
            : Expense::where('property_id', $unit->property_id);

        $incomeYear = (int) $unit->incomes()->where('income_year', $year)->whereNotNull('received_on')->sum('amount_ore');
        $expenseYear = (int) (clone $expenseQuery)->where('income_year', $year)->sum('amount_ore');

        $expenses = (clone $expenseQuery)->where('income_year', $year)
            ->with(['document', 'analysis'])->orderByDesc('date')
            ->get()->map(fn (Expense $e) => ['date' => $e->date, 'expense' => $e]);

        // Rent ledger for the year.
        $incomesByMonth = $unit->incomes()->where('period_year', $year)->get()->keyBy('period_month');
        $now = now();
        $ledger = collect(range(1, 12))->map(function (int $m) use ($year, $incomesByMonth, $tenancy, $now) {
            $inc = $incomesByMonth->get($m);
            $monthStart = Carbon::create($year, $m, 1);
            $active = $tenancy && $tenancy->starts_on->lte($monthStart->copy()->endOfMonth())
                && ($tenancy->ends_on === null || $tenancy->ends_on->gte($monthStart));
            $pastOrNow = $monthStart->lte($now);

            $statusKind = match (true) {
                $inc && $inc->received_on !== null => 'paid',
                $inc && $pastOrNow => 'overdue',
                $inc => 'upcoming',
                $active && $pastOrNow => 'missing',
                $active => 'upcoming',
                default => 'inactive',
            };

            return [
                'month' => $m,
                'label' => $monthStart->locale('nb')->isoFormat('MMMM'),
                'income' => $inc,
                'amount' => $inc?->amount_ore ?? ($active ? $tenancy->monthly_rent_ore : null),
                'status' => $statusKind,
            ];
        })->filter(fn ($r) => $r['status'] !== 'inactive')->values();

        $outstanding = (int) $unit->incomes()->where('period_year', $year)->whereNull('received_on')
            ->whereHas('unit')->get()
            ->filter(fn (Income $i) => Carbon::create($year, $i->period_month, 1)->lte($now))
            ->sum(fn (Income $i) => $i->amount_ore->ore);

        $pastTenancies = $unit->tenancies()->with('tenant')->orderByDesc('starts_on')->get()
            ->reject(fn (Tenancy $t) => $tenancy && $t->id === $tenancy->id);

        return [
            'isBuilding' => $isBuilding,
            'status' => $status,
            'tenancy' => $tenancy,
            'monthlyRent' => $tenancy?->monthly_rent_ore ?? Money::zero(),
            'incomeYear' => new Money($incomeYear),
            'expenseYear' => new Money($expenseYear),
            'netYear' => new Money($incomeYear - $expenseYear),
            'utgiftLabel' => $isBuilding ? 'Egne utgifter '.$year : 'Utgifter '.$year,
            'expenses' => $expenses,
            'ledger' => $ledger,
            'ledgerHasMissing' => $ledger->contains(fn ($r) => $r['status'] === 'missing'),
            'outstanding' => new Money((int) $outstanding),
            'pastTenancies' => $pastTenancies,
            'year' => $year,
            'since' => $tenancy ? $tenancy->starts_on->locale('nb')->isoFormat('MMMM YYYY') : '—',
        ];
    }
};
?>

<div>
    @php($property = $unit->property)
    @if ($isBuilding)
        <a href="{{ route('properties.show', $property) }}" class="text-sm text-muted hover:text-ink">← {{ $property->name }}</a>
    @else
        <a href="{{ route('properties.index') }}" class="text-sm text-muted hover:text-ink">← Boliger</a>
    @endif

    <div class="mb-9 mt-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            @if ($isBuilding)
                <div class="mb-1.5 text-[13px] font-semibold text-terra">Del av {{ $property->name }}</div>
            @endif
            <div class="flex items-center gap-3.5">
                <h1 class="font-display text-3xl font-bold tracking-tight md:text-[34px]">{{ $unit->name }}@if ($unit->code) <span class="text-2xl font-semibold text-faint">({{ $unit->code }})</span>@endif</h1>
                <x-status-pill :status="$status" />
            </div>
            <div class="mt-2 text-[15px] text-muted">
                {{ $property->address }}, {{ $property->postal_code }} {{ $property->city }} · {{ $unit->unit_type }}
            </div>
        </div>
        <div class="flex gap-2.5">
            <button type="button" wire:click="edit" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold transition-colors hover:border-faint">Rediger</button>
            <a href="{{ route('expenses.create') }}" class="rounded-[10px] bg-terra px-4 py-2.5 text-sm font-semibold text-white">+ Utgift</a>
        </div>
    </div>

    <div class="grid grid-cols-2 border-y border-line md:grid-cols-4">
        <x-stat class="py-5 md:py-6" label="Månedsleie" :value="$monthlyRent->format()" />
        <x-stat class="py-5 md:border-l md:border-line md:py-6 md:pl-[30px]" label="Inntekt {{ $year }}" :value="$incomeYear->format()" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" :label="$utgiftLabel" :value="$expenseYear->format()" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Netto {{ $year }}" tone="positive" :value="$netYear->format()" />
    </div>

    <div class="grid grid-cols-1 gap-10 pt-8 md:grid-cols-[1.5fr_1fr] md:gap-14 md:pt-10">
        <div>
            {{-- Rent ledger --}}
            <div class="mb-2 flex items-center justify-between">
                <div class="text-[13px] uppercase tracking-[0.08em] text-faint">Husleie {{ $year }}</div>
                <div class="flex items-center gap-3">
                    @unless ($outstanding->isZero())
                        <span class="text-[12.5px] font-medium text-negative">{{ $outstanding->format() }} utestående</span>
                    @endunless
                    @if ($ledgerHasMissing)
                        <button type="button" wire:click="generateRent" class="text-[13px] font-semibold text-terra hover:opacity-80">+ Generér husleie</button>
                    @endif
                </div>
            </div>

            @forelse ($ledger as $row)
                <div class="flex items-center justify-between border-b border-line py-3">
                    <div class="flex items-center gap-3.5">
                        <span @class([
                            'size-2 shrink-0 rounded-full',
                            'bg-positive' => $row['status'] === 'paid',
                            'bg-negative' => $row['status'] === 'overdue',
                            'bg-vacant' => in_array($row['status'], ['upcoming', 'missing']),
                        ])></span>
                        <div>
                            <div class="text-[15px] font-medium capitalize">{{ $row['label'] }}</div>
                            <div class="mt-0.5 text-xs">
                                @switch($row['status'])
                                    @case('paid')
                                        <span class="text-positive">Betalt {{ \App\Support\Format::dateLong($row['income']->received_on) }}</span>
                                        @break
                                    @case('overdue')
                                        <span class="text-negative">Forfalt</span>
                                        @break
                                    @case('upcoming')
                                        <span class="text-faint">Kommende</span>
                                        @break
                                    @case('missing')
                                        <span class="text-faint">Ikke ført</span>
                                        @break
                                @endswitch
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-[15px] font-semibold {{ $row['status'] === 'paid' ? 'text-positive' : 'text-ink' }}">{{ $row['amount']?->format() ?? '—' }}</div>
                        @if ($row['income'] && $row['status'] !== 'paid')
                            <button type="button" wire:click="openPayment({{ $row['income']->id }})" class="rounded-[8px] border border-line-strong px-2.5 py-1 text-[12px] font-semibold text-terra transition-colors hover:border-terra">Registrer</button>
                        @elseif ($row['status'] === 'paid')
                            <button type="button" wire:click="markUnpaid({{ $row['income']->id }})" title="Angre betaling" class="text-[12px] text-faint hover:text-negative">Angre</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-muted">
                    @if ($tenancy) Ingen husleie ført for {{ $year }}. Trykk «Generér husleie».
                    @else Ingen aktivt leieforhold. @endif
                </p>
            @endforelse

            {{-- Expenses --}}
            <div class="mb-2 mt-9 text-[13px] uppercase tracking-[0.08em] text-faint">{{ $utgiftLabel }}</div>
            @forelse ($expenses as $row)
                @php($e = $row['expense'])
                @php($ai = $e->analysis?->suggested ?? null)
                <div x-data="{ open: false }" class="border-b border-line">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between py-[15px] text-left">
                        <div class="flex items-start gap-3.5">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-terra"></span>
                            <div>
                                <div class="text-[15px] font-medium">{{ $e->description ?: ($e->vendor ?: ($e->category ?: $e->type->label())) }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-faint">
                                    <span>{{ \App\Support\Format::dateLong($e->date) }}</span>
                                    <span class="rounded-full bg-teal-soft px-2 py-0.5 font-medium text-teal">{{ $e->type->label() }}</span>
                                    @if ($e->category)<span>· {{ $e->category }}</span>@endif
                                    @if ($e->document_id)
                                        <span class="inline-flex items-center gap-1 text-terra">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15V8a5 5 0 0 0-10 0v9a3 3 0 0 0 6 0V9"/></svg>
                                            Bilag
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="text-[15px] font-semibold text-ink">{{ $e->amount_ore->negate()->format() }}</div>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-faint transition-transform" ::class="open && 'rotate-180'"><path d="M6 9l6 6 6-6"/></svg>
                        </div>
                    </button>
                    <div x-show="open" x-cloak x-transition class="pb-4 pl-[26px]">
                        <div class="rounded-xl border border-line bg-surface p-4 text-[13px]">
                            <div class="grid grid-cols-1 gap-y-2 sm:grid-cols-2 sm:gap-x-6">
                                <div class="flex justify-between border-b border-line py-1.5 sm:border-0"><span class="text-muted">Kostnadstype</span><span class="font-medium">{{ $e->type->label() }}</span></div>
                                <div class="flex justify-between border-b border-line py-1.5 sm:border-0"><span class="text-muted">Behandling</span><span class="text-right">{{ $e->type->taxTreatment() }}</span></div>
                                <div class="flex justify-between border-b border-line py-1.5 sm:border-0"><span class="text-muted">Kategori</span><span class="font-medium">{{ $e->category ?: '—' }}</span></div>
                                <div class="flex justify-between border-b border-line py-1.5 sm:border-0"><span class="text-muted">Beløp</span><span class="font-medium">{{ $e->amount_ore->format() }}</span></div>
                                @if ($e->vendor)<div class="flex justify-between border-b border-line py-1.5 sm:border-0"><span class="text-muted">Leverandør</span><span class="text-right font-medium">{{ $e->vendor }}</span></div>@endif
                            </div>
                            @if ($e->description && ($e->vendor || $e->category))
                                <div class="mt-2 border-t border-line pt-2 text-muted">{{ $e->description }}</div>
                            @endif
                            @if ($ai && ! empty($ai['rationale']))
                                @php($conf = ! empty($ai['confidence']) ? ' ('.(int) round($ai['confidence'] * 100).' %)' : '')
                                <div class="mt-2 text-[12.5px] text-ink-soft">✨ AI-forslag{{ $conf }}: {{ $ai['rationale'] }}</div>
                            @endif
                            <div class="mt-3 flex items-center gap-3 border-t border-line pt-3">
                                @if ($e->document)
                                    <a href="{{ route('documents.show', $e->document) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-[9px] bg-terra px-3.5 py-2 text-[13px] font-semibold text-white transition-opacity hover:opacity-90">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Vis bilag
                                    </a>
                                    <span class="truncate text-xs text-faint">{{ $e->document->original_filename }}</span>
                                @else
                                    <span class="text-xs text-faint">Ingen bilag lastet opp.</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-muted">Ingen utgifter registrert i {{ $year }}.</p>
            @endforelse
            @if ($isBuilding)
                <p class="mt-4 text-[13px] leading-relaxed text-faint">Felleskostnader for bygget vises på bygård-siden og inngår ikke i enhetens netto.</p>
            @endif
        </div>

        {{-- Right column: tenancy --}}
        <div class="flex flex-col gap-6">
            <div>
                <div class="mb-2 text-[13px] uppercase tracking-[0.08em] text-faint">Leieforhold</div>
                <x-card class="p-0">
                    @if ($tenancy)
                        <div class="border-b border-line px-[22px] py-4">
                            <div class="text-[17px] font-semibold">{{ $tenancy->tenant?->name }}</div>
                            <div class="mt-2 flex flex-col gap-1.5 text-[13px] text-muted">
                                @if ($tenancy->tenant?->email)
                                    <a href="mailto:{{ $tenancy->tenant->email }}" class="inline-flex items-center gap-2 hover:text-terra">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>{{ $tenancy->tenant->email }}
                                    </a>
                                @endif
                                @if ($tenancy->tenant?->phone)
                                    <a href="tel:{{ $tenancy->tenant->phone }}" class="inline-flex items-center gap-2 hover:text-terra">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>{{ $tenancy->tenant->phone }}
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="px-[22px] py-1">
                            <div class="flex justify-between border-b border-line py-3 text-sm"><span class="text-muted">Månedsleie</span><span class="font-semibold">{{ $tenancy->monthly_rent_ore->format() }}</span></div>
                            <div class="flex justify-between border-b border-line py-3 text-sm"><span class="text-muted">Leieforhold</span><span class="font-medium">{{ $tenancy->starts_on->locale('nb')->isoFormat('MMM YYYY') }} – {{ $tenancy->ends_on ? $tenancy->ends_on->locale('nb')->isoFormat('MMM YYYY') : 'løpende' }}</span></div>
                            <div class="flex justify-between {{ $status === \App\Enums\UnitStatus::Vacant ? '' : 'border-b border-line' }} py-3 text-sm"><span class="text-muted">Depositum</span><span class="font-medium">{{ $tenancy->deposit_ore?->format() ?? '—' }}</span></div>
                        </div>
                        <div class="flex gap-2.5 px-[22px] py-4">
                            @if ($status !== \App\Enums\UnitStatus::Vacant)
                                <button type="button" wire:click="edit" class="flex-1 rounded-[9px] border border-line-strong bg-surface py-2.5 text-[13px] font-semibold transition-colors hover:border-faint">Endre leie / leietaker</button>
                                <button type="button" wire:click="endTenancy" wire:confirm="Avslutte leieforholdet til {{ $tenancy->tenant?->name }}? Enheten settes som ledig." class="flex-1 rounded-[9px] border border-line-strong bg-surface py-2.5 text-[13px] font-semibold text-negative transition-colors hover:border-negative">Avslutt</button>
                            @else
                                <button type="button" wire:click="openNewTenancy" class="flex-1 rounded-[9px] bg-terra py-2.5 text-[13px] font-semibold text-white transition-opacity hover:opacity-90">+ Nytt leieforhold</button>
                            @endif
                        </div>
                    @else
                        <div class="px-[22px] py-7 text-center">
                            <div class="text-[15px] font-medium">Ledig</div>
                            <div class="mt-1 text-[13px] text-muted">Ingen aktivt leieforhold på enheten.</div>
                            <button type="button" wire:click="openNewTenancy" class="mt-4 w-full rounded-[9px] bg-terra py-2.5 text-[13px] font-semibold text-white transition-opacity hover:opacity-90">+ Nytt leieforhold</button>
                        </div>
                    @endif
                </x-card>
            </div>

            @if ($pastTenancies->isNotEmpty())
                <div>
                    <div class="mb-2 text-[13px] uppercase tracking-[0.08em] text-faint">Tidligere leieforhold</div>
                    <x-card class="px-[22px] py-1">
                        @foreach ($pastTenancies as $pt)
                            <div class="flex items-center justify-between {{ ! $loop->last ? 'border-b border-line' : '' }} py-3 text-sm">
                                <div>
                                    <div class="font-medium">{{ $pt->tenant?->name ?? 'Ukjent' }}</div>
                                    <div class="mt-0.5 text-xs text-faint">{{ $pt->starts_on->locale('nb')->isoFormat('MMM YYYY') }} – {{ $pt->ends_on ? $pt->ends_on->locale('nb')->isoFormat('MMM YYYY') : '—' }}</div>
                                </div>
                                <span class="text-[13px] text-muted">{{ $pt->monthly_rent_ore->format() }}</span>
                            </div>
                        @endforeach
                    </x-card>
                </div>
            @endif
        </div>
    </div>

    {{-- ───────── Modals ───────── --}}
    @if ($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4 backdrop-blur-[2px]"
            wire:click.self="closeModal" wire:keydown.escape="closeModal">
            <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface shadow-2xl">

                {{-- Edit unit + tenancy --}}
                @if ($modal === 'unit')
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <div class="text-lg font-semibold">Rediger enhet</div>
                        <button type="button" wire:click="closeModal" class="text-xl leading-none text-faint hover:text-ink" aria-label="Lukk">&times;</button>
                    </div>
                    <form wire:submit="saveUnit" class="flex min-h-0 flex-1 flex-col">
                        <div class="grid grid-cols-1 gap-4 overflow-y-auto overflow-x-hidden px-6 py-5 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Navn</label>
                                <input wire:model="f_name" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                @error('f_name') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror</div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Bruksenhet (kode)</label>
                                <input wire:model="f_code" placeholder="F.eks. H0101" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Type</label>
                                <input wire:model="f_unit_type" placeholder="F.eks. 3-roms" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Areal (m²)</label>
                                <input wire:model="f_area" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Rom</label>
                                <input wire:model="f_rooms" inputmode="decimal" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>

                            @if ($tenancy)
                                <div class="sm:col-span-2 mt-1 border-t border-line pt-4 text-[13px] uppercase tracking-[0.08em] text-faint">Leietaker & leieforhold</div>
                                <div class="sm:col-span-2"><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Leietaker</label>
                                    <input wire:model="f_tenant" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                                <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">E-post</label>
                                    <input wire:model="f_email" type="email" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                    @error('f_email') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror</div>
                                <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Telefon</label>
                                    <input wire:model="f_phone" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                                <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Månedsleie (kr)</label>
                                    <input wire:model="f_rent" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                                <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Depositum (kr)</label>
                                    <input wire:model="f_deposit" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                                <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Startdato</label>
                                    <input wire:model="f_starts" type="date" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                                <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Sluttdato (valgfritt)</label>
                                    <input wire:model="f_ends" type="date" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                            @endif
                        </div>
                        <div class="flex items-center justify-end gap-2.5 border-t border-line px-6 py-4">
                            <button type="button" wire:click="closeModal" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold hover:border-faint">Avbryt</button>
                            <button type="submit" class="rounded-[10px] bg-terra px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Lagre</button>
                        </div>
                    </form>

                {{-- New tenancy --}}
                @elseif ($modal === 'tenancy')
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <div class="text-lg font-semibold">Nytt leieforhold</div>
                        <button type="button" wire:click="closeModal" class="text-xl leading-none text-faint hover:text-ink" aria-label="Lukk">&times;</button>
                    </div>
                    <form wire:submit="saveTenancy" class="flex min-h-0 flex-1 flex-col">
                        <div class="grid grid-cols-1 gap-4 overflow-y-auto overflow-x-hidden px-6 py-5 sm:grid-cols-2">
                            @if ($tenancy)
                                <p class="sm:col-span-2 rounded-lg bg-panel px-3 py-2 text-[12.5px] text-muted">Det nåværende leieforholdet avsluttes dagen før det nye starter.</p>
                            @endif
                            <div class="sm:col-span-2"><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Leietaker</label>
                                <input wire:model="n_tenant" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                @error('n_tenant') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror</div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">E-post</label>
                                <input wire:model="n_email" type="email" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                @error('n_email') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror</div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Telefon</label>
                                <input wire:model="n_phone" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Månedsleie (kr)</label>
                                <input wire:model="n_rent" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                @error('n_rent') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror</div>
                            <div><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Depositum (kr)</label>
                                <input wire:model="n_deposit" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra"></div>
                            <div class="sm:col-span-2"><label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Startdato</label>
                                <input wire:model="n_starts" type="date" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                @error('n_starts') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror</div>
                        </div>
                        <div class="flex items-center justify-end gap-2.5 border-t border-line px-6 py-4">
                            <button type="button" wire:click="closeModal" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold hover:border-faint">Avbryt</button>
                            <button type="submit" class="rounded-[10px] bg-terra px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Opprett leieforhold</button>
                        </div>
                    </form>

                {{-- Register payment --}}
                @elseif ($modal === 'payment')
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <div class="text-lg font-semibold">Registrer innbetaling</div>
                        <button type="button" wire:click="closeModal" class="text-xl leading-none text-faint hover:text-ink" aria-label="Lukk">&times;</button>
                    </div>
                    <form wire:submit="confirmPayment" class="flex flex-col">
                        <div class="px-6 py-5">
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Betalt dato</label>
                            <input wire:model="payDate" type="date" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                            @error('payDate') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center justify-end gap-2.5 border-t border-line px-6 py-4">
                            <button type="button" wire:click="closeModal" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold hover:border-faint">Avbryt</button>
                            <button type="submit" class="rounded-[10px] bg-positive px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Marker som betalt</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
