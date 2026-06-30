@props(['status'])

@php
    /** @var \App\Enums\UnitStatus $status */
    $tone = match ($status->tone()) {
        'positive' => ['bg-positive-soft', 'text-positive', 'bg-positive'],
        'accent' => ['bg-negative-soft', 'text-negative', 'bg-negative'],
        default => ['bg-vacant-soft', 'text-muted', 'bg-vacant'],
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone[0] }} {{ $tone[1] }}">
    <span class="size-1.5 rounded-full {{ $tone[2] }}"></span>{{ $status->label() }}
</span>
