@props(['active' => false])

<a {{ $attributes->class([
    'rounded-[8px] px-3.5 py-2 text-sm font-medium transition-colors',
    'bg-chip text-ink' => $active,
    'text-muted hover:text-ink hover:bg-chip/60' => ! $active,
]) }}>
    {{ $slot }}
</a>
