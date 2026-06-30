@props(['label', 'value', 'sub' => null, 'tone' => 'ink'])

@php
    $toneClass = match ($tone) {
        'positive' => 'text-positive',
        'terra' => 'text-terra',
        'teal' => 'text-terra',
        'negative' => 'text-negative',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes }}>
    <div class="text-[11.5px] uppercase tracking-[0.09em] text-faint">{{ $label }}</div>
    <div class="tnum font-display mt-2.5 text-[30px] font-semibold leading-none tracking-[-0.02em] {{ $toneClass }}">{{ $value }}</div>
    @if ($sub)
        <div class="mt-1.5 text-[13px] text-faint">{{ $sub }}</div>
    @endif
</div>
