<?php

use App\Enums\TripSource;
use App\Models\Property;
use App\Models\TaxYearSetting;
use App\Models\Trip;
use App\Models\TripFavorite;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public string $date = '';
    public string $purpose = '';
    public string $property_id = '';   // '' = Felles (no property)
    public string $distance_km = '';
    public bool $round_trip = false;   // tur/retur → distance counted both ways
    public string $favoriteLabel = ''; // optional name when saving a favorite

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function save(): void
    {
        // property_id is a controlled select ('' = Felles); resolved to null below,
        // so it stays out of validation (an empty string would fail `integer`).
        $this->validate([
            'date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:255'],
            'distance_km' => ['required', 'integer', 'min:1'],
        ], [
            'date.required' => 'Velg en dato.',
            'purpose.required' => 'Fyll inn et formål.',
            'distance_km.required' => 'Fyll inn antall km.',
            'distance_km.min' => 'Antall km må være minst 1.',
        ]);

        $year = (int) date('Y', strtotime($this->date));

        // distance_km stores the FULL distance driven; tur/retur doubles the leg
        // the user entered (so the deduction and km totals stay simple sums).
        $km = (int) $this->distance_km * ($this->round_trip ? 2 : 1);

        Trip::create([
            'property_id' => $this->property_id ?: null,
            'date' => $this->date,
            'purpose' => $this->purpose,
            'distance_km' => $km,
            'round_trip' => $this->round_trip,
            // Rate is snapshotted from the income year's tax settings; the model's
            // saving hook derives income_year and deduction_ore (km × rate).
            'rate_ore_per_km' => TaxYearSetting::forYear($year)->mileage_rate_ore_per_km,
            'source' => TripSource::Web->value,
            'created_by' => Auth::id(),
        ]);

        $this->reset(['purpose', 'property_id', 'distance_km', 'round_trip']);
        $this->date = now()->format('Y-m-d');
    }

    /** Tap a saved route to pre-fill the form (destination, distance, purpose). */
    public function useFavorite(int $id): void
    {
        $fav = TripFavorite::find($id);
        if (! $fav) {
            return;
        }

        $this->property_id = (string) ($fav->property_id ?? '');
        $this->distance_km = (string) $fav->distance_km;
        $this->round_trip = (bool) $fav->round_trip;
        $this->purpose = $fav->purpose ?? '';
    }

    /** Save the current form as a reusable favorite route. */
    public function saveFavorite(): void
    {
        $this->validate(
            ['distance_km' => ['required', 'integer', 'min:1']],
            ['distance_km.required' => 'Fyll inn antall km før du lagrer en favoritt.']
        );

        $property = $this->property_id ? Property::find($this->property_id) : null;
        $label = trim($this->favoriteLabel);

        TripFavorite::create([
            'label' => $label !== '' ? $label : ($property?->name ?? ($this->purpose ?: 'Felles tur')),
            'property_id' => $this->property_id ?: null,
            'distance_km' => (int) $this->distance_km,
            'round_trip' => $this->round_trip,
            'purpose' => $this->purpose ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->favoriteLabel = '';
    }

    public function deleteFavorite(int $id): void
    {
        TripFavorite::whereKey($id)->delete();
    }

    /** Soft-delete a trip — kept in the DB for defensible history (brief §3.9). */
    public function deleteTrip(int $id): void
    {
        Trip::whereKey($id)->delete();
    }

    public function with(): array
    {
        $year = (int) (Trip::max('income_year') ?? now()->year);

        $trips = Trip::with('property')->where('income_year', $year)
            ->orderByDesc('date')->orderByDesc('id')->get();

        $km = (int) $trips->sum('distance_km');
        $rate = TaxYearSetting::forYear($year)->mileage_rate_ore_per_km;

        return [
            'year' => $year,
            'trips' => $trips,
            'kmYear' => $km,
            'deduction' => new Money((int) $trips->sum(fn (Trip $t) => $t->deduction_ore->ore)),
            'tripCount' => $trips->count(),
            'avgKm' => $trips->count() ? (int) round($km / $trips->count()) : 0,
            'rateLabel' => Money::fromOre($rate)->format(symbol: false, decimals: true),
            'properties' => Property::orderBy('name')->get(),
            'favorites' => TripFavorite::with('property')->orderBy('label')->get(),
        ];
    }
};
?>

<div>
    <h1 class="text-3xl font-bold tracking-tight md:text-[34px]">Kjørebok</h1>
    <p class="mb-8 mt-1 text-[15px] text-muted">Kjøring knyttet til drift og vedlikehold · sats {{ $rateLabel }} kr/km.</p>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 border-y border-line md:grid-cols-4">
        <x-stat class="py-5 md:py-6" label="Km i {{ $year }}" :value="\App\Support\Format::number($kmYear)" />
        <x-stat class="py-5 md:border-l md:border-line md:py-6 md:pl-[30px]" label="Beregnet fradrag" tone="positive" :value="$deduction->format()" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Antall turer" :value="(string) $tripCount" />
        <x-stat class="border-t border-line py-5 md:border-l md:border-t-0 md:py-6 md:pl-[30px]" label="Snitt per tur" :value="$avgKm.' km'" />
    </div>

    {{-- Favoritter — tap a saved route to pre-fill the form --}}
    <div class="mt-8">
        <div class="mb-2.5 text-[13px] uppercase tracking-[0.08em] text-faint">Favoritter</div>
        <div class="flex flex-wrap items-center gap-2">
            @forelse ($favorites as $fav)
                <span class="inline-flex items-center gap-2 rounded-full border border-line-strong bg-surface py-2 pl-3.5 pr-2.5 text-[13.5px]">
                    <button type="button" wire:click="useFavorite({{ $fav->id }})" class="font-medium transition-colors hover:text-terra">{{ $fav->label }} · {{ $fav->distance_km }} km @if ($fav->round_trip)<span class="rounded bg-chip px-1 py-0.5 text-[10px] font-semibold text-muted">t/r</span>@endif</button>
                    <button type="button" wire:click="deleteFavorite({{ $fav->id }})" class="text-base leading-none text-faint transition-colors hover:text-terra" title="Fjern favoritt">×</button>
                </span>
            @empty
                <span class="text-sm text-muted">Ingen favoritter ennå — fyll inn en tur og trykk «Lagre som favoritt».</span>
            @endforelse
        </div>
    </div>

    {{-- Add a trip --}}
    <form wire:submit.prevent="save" class="mt-6">
        <x-card class="p-5 md:p-6">
            <div class="grid grid-cols-1 gap-3.5 md:grid-cols-[120px_1.6fr_1fr_90px_120px] md:items-end">
                <div>
                    <label class="mb-1.5 block text-xs text-muted">Dato</label>
                    <input wire:model="date" type="date" class="w-full rounded-[9px] border border-line-strong bg-surface px-3 py-2.5 text-sm outline-none focus:border-terra">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs text-muted">Formål</label>
                    <input wire:model="purpose" placeholder="F.eks. befaring" class="w-full rounded-[9px] border border-line-strong bg-surface px-3 py-2.5 text-sm outline-none focus:border-terra">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs text-muted">Eiendom</label>
                    <select wire:model="property_id" class="w-full appearance-none rounded-[9px] border border-line-strong bg-surface px-3 py-2.5 text-sm">
                        <option value="">Felles / ingen</option>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs text-muted">Km</label>
                    <input wire:model="distance_km" inputmode="numeric" placeholder="0" class="w-full rounded-[9px] border border-line-strong bg-surface px-3 py-2.5 text-sm outline-none focus:border-terra">
                </div>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-[9px] bg-terra px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-60">
                    + Legg til
                </button>
            </div>
            <div class="mt-3.5 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="$toggle('round_trip')" @class([
                    'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-[13px] font-semibold transition-colors',
                    'border-terra bg-terra-soft text-terra' => $round_trip,
                    'border-line-strong text-muted hover:border-faint' => ! $round_trip,
                ])>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>
                    Tur/retur ×2
                </button>
                @if ($round_trip && (int) $distance_km > 0)
                    <span class="text-[12.5px] text-faint">= {{ \App\Support\Format::number((int) $distance_km * 2) }} km tur/retur</span>
                @endif
                <div class="ml-auto flex items-center gap-2">
                    <input wire:model="favoriteLabel" placeholder="Navn på favoritt" class="w-40 rounded-[9px] border border-line-strong bg-surface px-3 py-1.5 text-[13px] outline-none focus:border-terra">
                    <button type="button" wire:click="saveFavorite" class="whitespace-nowrap text-[13px] font-semibold text-terra transition-opacity hover:opacity-80">★ Lagre</button>
                </div>
                @php $err = $errors->first(); @endphp
                @if ($err) <p class="w-full text-[13px] text-terra">{{ $err }}</p> @endif
            </div>
        </x-card>
    </form>

    {{-- Trips list --}}
    <div class="mt-7">
        <div class="mb-1 text-[13px] uppercase tracking-[0.08em] text-faint">Turer {{ $year }}</div>
        @forelse ($trips as $trip)
            <div class="group flex items-center justify-between border-b border-line py-[15px]">
                <div>
                    <div class="text-[15px] font-medium">{{ $trip->purpose }}</div>
                    <div class="mt-0.5 text-xs text-faint">{{ \App\Support\Format::dateLong($trip->date) }} · {{ $trip->property?->name ?? 'Felles' }}</div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-[15px] font-semibold">
                            {{ \App\Support\Format::number($trip->distance_km) }} km
                            @if ($trip->round_trip)<span class="ml-0.5 rounded bg-chip px-1.5 py-0.5 align-middle text-[10px] font-semibold text-muted">t/r</span>@endif
                        </div>
                        <div class="mt-0.5 text-xs text-positive">{{ $trip->deduction_ore->format() }}</div>
                    </div>
                    <button type="button" wire:click="deleteTrip({{ $trip->id }})"
                        wire:confirm="Slette turen «{{ $trip->purpose }}»?"
                        class="p-1 text-faint transition-colors hover:text-terra md:opacity-0 md:group-hover:opacity-100"
                        title="Slett tur" aria-label="Slett tur">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                    </button>
                </div>
            </div>
        @empty
            <p class="py-4 text-sm text-muted">Ingen turer registrert i {{ $year }} ennå.</p>
        @endforelse
    </div>
</div>
