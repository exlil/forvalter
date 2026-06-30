@props(['href' => null])

<a href="{{ $href ?? route('dashboard') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <span class="flex size-7 items-center justify-center rounded-[8px] bg-terra">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
    </span>
    <span class="font-display text-[19px] font-bold tracking-[-0.02em] text-ink">Forvalter</span>
</a>
