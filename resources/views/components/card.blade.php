@props(['padded' => true])

<div {{ $attributes->class(['rounded-2xl border border-line bg-surface', 'p-6' => $padded]) }}>
    {{ $slot }}
</div>
