<?php

use App\Enums\UnitStatus;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Property;
use App\Models\Unit;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public bool $creating = false;
    public string $c_name = '', $c_address = '', $c_postal = '', $c_city = 'Bergen';
    public string $c_kind = 'standalone';          // 'standalone' | 'building'
    public string $c_units = '4';                  // bygård unit count
    public string $c_type = '';                    // frittstående unit type
    public string $c_purchase_date = '', $c_purchase_price = '';

    public function openCreate(): void
    {
        $this->reset(['c_name', 'c_address', 'c_postal', 'c_kind', 'c_units', 'c_type', 'c_purchase_date', 'c_purchase_price']);
        $this->c_city = 'Bergen';
        $this->c_kind = 'standalone';
        $this->c_units = '4';
        $this->resetValidation();
        $this->creating = true;
    }

    public function closeCreate(): void
    {
        $this->creating = false;
    }

    public function saveProperty()
    {
        $this->validate([
            'c_name' => ['required', 'string', 'max:255'],
            'c_address' => ['nullable', 'string', 'max:255'],
            'c_postal' => ['nullable', 'string', 'max:12'],
            'c_city' => ['nullable', 'string', 'max:120'],
            'c_kind' => ['required', 'in:standalone,building'],
            'c_units' => ['nullable', 'integer', 'min:2', 'max:50', 'required_if:c_kind,building'],
            'c_type' => ['nullable', 'string', 'max:50'],
            'c_purchase_date' => ['nullable', 'date'],
            'c_purchase_price' => ['nullable', 'string'],
        ], [
            'c_name.required' => 'Eiendommen må ha et navn.',
            'c_units.required_if' => 'Oppgi antall enheter for en bygård.',
        ]);

        $property = Property::create([
            'name' => $this->c_name,
            'address' => $this->c_address ?: $this->c_name,
            'postal_code' => $this->c_postal ?: null,
            'city' => $this->c_city ?: null,
            'property_type' => $this->c_kind === 'building' ? 'Bygård' : 'Leilighet',
            'purchase_date' => $this->c_purchase_date ?: null,
            'purchase_price_ore' => $this->c_purchase_price !== '' ? Money::fromKronerString($this->c_purchase_price)->ore : null,
        ]);

        if ($this->c_kind === 'building') {
            $n = (int) $this->c_units;
            for ($i = 1; $i <= $n; $i++) {
                Unit::create([
                    'property_id' => $property->id,
                    'name' => 'Leil. '.$i,
                    'code' => sprintf('H0%d01', $i),
                ]);
            }

            return $this->redirect(route('properties.show', $property), navigate: true);
        }

        $unit = Unit::create([
            'property_id' => $property->id,
            'name' => $this->c_name,
            'unit_type' => $this->c_type ?: null,
        ]);

        return $this->redirect(route('units.show', $unit), navigate: true);
    }

    public function with(): array
    {
        $year = (int) (Income::max('period_year') ?? now()->year);
        $month = (int) (Income::where('period_year', $year)->max('period_month') ?? now()->month);

        $properties = Property::withCount('units')
            ->with(['units.currentTenancy.tenant'])
            ->orderByDesc('units_count')
            ->get();

        $buildings = $properties->filter->isBuilding()->map(function (Property $p) use ($year, $month) {
            $samlet = $p->monthlyRent();
            $felles = $p->felleskostnaderForMonth($year, $month);

            return [
                'property' => $p,
                'unitCount' => $p->units_count,
                'samletLeie' => $samlet,
                'fellesMnd' => $felles,
                'nettoBygg' => $samlet->subtract($felles),
                'units' => $p->units->map(fn (Unit $u) => [
                    'short' => $u->name,
                    'status' => $u->status(),
                    'rent' => $u->currentTenancy?->monthly_rent_ore,
                ]),
            ];
        })->values();

        $standalone = $properties->reject->isBuilding()->map(function (Property $p) use ($year) {
            $unit = $p->units->first();
            $income = (int) $unit?->incomes()->where('income_year', $year)->whereNotNull('received_on')->sum('amount_ore');
            $expense = (int) Expense::where('property_id', $p->id)->where('income_year', $year)->sum('amount_ore');

            return [
                'unit' => $unit,
                'status' => $unit?->status() ?? UnitStatus::Vacant,
                'type' => $unit?->unit_type,
                'tenant' => $unit?->currentTenancy?->tenant?->name,
                'rent' => $unit?->currentTenancy?->monthly_rent_ore,
                'netto' => new Money($income - $expense),
            ];
        })->values();

        return [
            'year' => $year,
            'buildings' => $buildings,
            'standalone' => $standalone,
            'subtitle' => collect([
                $buildings->count() ? $buildings->count().' bygård' : null,
                $standalone->count() ? $standalone->count().' frittstående' : null,
                $properties->sum('units_count').' enheter',
            ])->filter()->implode(' · '),
        ];
    }
};
?>

@php
    $dotClass = fn (UnitStatus $s) => match ($s) {
        UnitStatus::Rented => 'bg-positive',
        UnitStatus::Arrears => 'bg-negative',
        UnitStatus::Vacant => 'bg-vacant',
    };
@endphp

<div>
    <div class="mb-8 flex items-end justify-between">
        <div>
            <h1 class="text-[34px] font-bold tracking-tight">Boliger</h1>
            <p class="mt-1.5 text-[15px] text-muted">{{ $subtitle }}</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold transition-colors hover:border-faint">+ Legg til eiendom</button>
    </div>

    {{-- Bygårder --}}
    @foreach ($buildings as $b)
        <a href="{{ route('properties.show', $b['property']) }}"
            class="mb-[18px] block rounded-2xl border border-line bg-surface p-7 transition-shadow hover:shadow-sm">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="size-[46px] rounded-xl" style="background:repeating-linear-gradient(45deg,#EFE8DB,#EFE8DB 5px,#E7DECE 5px,#E7DECE 10px);"></div>
                    <div>
                        <div class="text-[19px] font-semibold tracking-tight">{{ $b['property']->name }}</div>
                        <span class="mt-1.5 inline-flex items-center rounded-full bg-vacant-soft px-2.5 py-1 text-xs font-semibold text-ink-soft">Bygård · {{ $b['unitCount'] }} enheter</span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="whitespace-nowrap text-xs text-faint">Netto bygg · mnd</div>
                    <div class="mt-1 whitespace-nowrap text-[22px] font-bold tracking-tight text-positive">{{ $b['nettoBygg']->format() }}</div>
                </div>
            </div>

            <div class="mt-[22px] grid grid-cols-2 gap-2.5 md:grid-cols-4">
                @foreach ($b['units'] as $u)
                    <div class="rounded-[10px] border border-line bg-panel px-3.5 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="size-[7px] rounded-full {{ $dotClass($u['status']) }}"></span>
                            <span class="text-[13px] font-semibold">{{ $u['short'] }}</span>
                        </div>
                        <div class="mt-1.5 text-[13px] text-muted">{{ $u['rent']?->format() ?? 'Ledig' }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-x-8 gap-y-3 border-t border-line pt-[18px]">
                <div><div class="text-xs text-faint">Samlet leie · mnd</div><div class="mt-1 text-[17px] font-semibold">{{ $b['samletLeie']->format() }}</div></div>
                <div><div class="text-xs text-faint">Felleskostnader · mnd</div><div class="mt-1 text-[17px] font-semibold">{{ $b['fellesMnd']->format() }}</div></div>
                <div class="ml-auto self-center text-[13px] font-semibold text-terra">Åpne bygård →</div>
            </div>
        </a>
    @endforeach

    {{-- Frittstående --}}
    @if ($standalone->isNotEmpty())
        <div class="mb-3.5 mt-[26px] text-[13px] uppercase tracking-[0.08em] text-faint">Frittstående</div>
        <div class="grid grid-cols-1 gap-[18px] md:grid-cols-2">
            @foreach ($standalone as $s)
                <a href="{{ route('units.show', $s['unit']) }}"
                    class="block rounded-2xl border border-line bg-surface p-6 transition-shadow hover:shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-lg font-semibold tracking-tight">{{ $s['unit']->name }}</div>
                            <div class="mt-1 text-[13px] text-faint">{{ $s['type'] }}</div>
                        </div>
                        <x-status-pill :status="$s['status']" />
                    </div>
                    <div class="mt-5 flex gap-9 border-t border-line pt-4">
                        <div><div class="text-xs text-faint">Månedsleie</div><div class="mt-1 whitespace-nowrap text-lg font-semibold">{{ $s['rent']?->format() ?? '—' }}</div></div>
                        <div><div class="text-xs text-faint">Netto {{ $year }}</div><div class="mt-1 whitespace-nowrap text-lg font-semibold text-positive">{{ $s['netto']->format() }}</div></div>
                        <div class="ml-auto hidden text-right md:block"><div class="text-xs text-faint">Leietaker</div><div class="mt-1 text-sm font-medium">{{ $s['tenant'] ?? 'Ledig' }}</div></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Add property modal --}}
    @if ($creating)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4 backdrop-blur-[2px]"
            wire:click.self="closeCreate" wire:keydown.escape="closeCreate">
            <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface shadow-2xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <div class="text-lg font-semibold">Legg til eiendom</div>
                    <button type="button" wire:click="closeCreate" class="text-xl leading-none text-faint hover:text-ink" aria-label="Lukk">&times;</button>
                </div>

                <form wire:submit="saveProperty" class="flex min-h-0 flex-1 flex-col">
                    <div class="grid grid-cols-1 gap-4 overflow-y-auto overflow-x-hidden px-6 py-5 sm:grid-cols-2">
                        {{-- Type --}}
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Type</label>
                            <div class="grid grid-cols-2 gap-2.5">
                                <button type="button" wire:click="$set('c_kind', 'standalone')" @class([
                                    'rounded-xl border p-3 text-left transition-colors',
                                    'border-terra bg-terra-soft' => $c_kind === 'standalone',
                                    'border-line-strong bg-surface hover:border-faint' => $c_kind !== 'standalone',
                                ])>
                                    <div class="text-sm font-semibold {{ $c_kind === 'standalone' ? 'text-terra' : 'text-ink' }}">Frittstående</div>
                                    <div class="mt-0.5 text-[11.5px] text-faint">Én enhet</div>
                                </button>
                                <button type="button" wire:click="$set('c_kind', 'building')" @class([
                                    'rounded-xl border p-3 text-left transition-colors',
                                    'border-terra bg-terra-soft' => $c_kind === 'building',
                                    'border-line-strong bg-surface hover:border-faint' => $c_kind !== 'building',
                                ])>
                                    <div class="text-sm font-semibold {{ $c_kind === 'building' ? 'text-terra' : 'text-ink' }}">Bygård</div>
                                    <div class="mt-0.5 text-[11.5px] text-faint">Flere enheter</div>
                                </button>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Navn</label>
                            <input wire:model="c_name" placeholder="F.eks. Blekenberg 36" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                            @error('c_name') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror
                        </div>

                        @if ($c_kind === 'building')
                            <div>
                                <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Antall enheter</label>
                                <input wire:model="c_units" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                                @error('c_units') <p class="mt-1 text-[13px] text-negative">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div>
                                <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Boligtype</label>
                                <input wire:model="c_type" placeholder="F.eks. 3-roms" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                            </div>
                        @endif

                        <div>
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Adresse</label>
                            <input wire:model="c_address" placeholder="(samme som navn)" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Postnr.</label>
                            <input wire:model="c_postal" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Poststed</label>
                            <input wire:model="c_city" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                        </div>

                        <div class="sm:col-span-2 mt-1 border-t border-line pt-4 text-[13px] uppercase tracking-[0.08em] text-faint">Kjøp (valgfritt)</div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Kjøpsdato</label>
                            <input wire:model="c_purchase_date" type="date" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-semibold text-ink-soft">Kjøpesum (kr)</label>
                            <input wire:model="c_purchase_price" inputmode="numeric" class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-2.5 text-[15px] outline-none focus:border-terra">
                        </div>
                        <p class="sm:col-span-2 text-[12.5px] text-faint">
                            @if ($c_kind === 'building')
                                Enhetene opprettes som «Leil. 1…». Du legger til leietaker og leie på hver enhet etterpå.
                            @else
                                Enheten opprettes som ledig — legg til leietaker og leie på enhetssiden etterpå.
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 border-t border-line px-6 py-4">
                        <button type="button" wire:click="closeCreate" class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold hover:border-faint">Avbryt</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveProperty" class="rounded-[10px] bg-terra px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Opprett</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
