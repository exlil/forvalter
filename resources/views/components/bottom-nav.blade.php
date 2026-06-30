{{-- Mobile bottom tab bar (design "Forvalter Mobil"). Hidden on desktop. --}}
@props(['count' => 0])
@php
    $tabs = [
        ['route' => 'dashboard', 'match' => ['dashboard'], 'label' => 'Hjem', 'icon' => 'home'],
        ['route' => 'properties.index', 'match' => ['properties.*', 'units.*'], 'label' => 'Boliger', 'icon' => 'building'],
        ['route' => 'intake', 'match' => ['intake'], 'label' => 'Innboks', 'icon' => 'inboxcircle'],
        ['route' => 'trips.index', 'match' => ['trips.*'], 'label' => 'Kjøring', 'icon' => 'trip'],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-20 flex items-start justify-around border-t border-line bg-surface px-4 pt-2.5 pb-[calc(env(safe-area-inset-bottom)+0.9rem)] md:hidden">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs(...$tab['match']); @endphp
        <a href="{{ route($tab['route']) }}"
            class="relative flex w-16 flex-col items-center gap-1.5 {{ $active ? 'text-terra' : 'text-faint' }}">
            @switch($tab['icon'])
                @case('home')
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    @break
                @case('building')
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                    @break
                @case('inboxcircle')
                    <span class="relative flex size-[23px] items-center justify-center rounded-full bg-terra">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.1" stroke-linejoin="round"><path d="M4 13h4l1.5 2.5h5L16 13h4M4 13l2-7h12l2 7M4 13v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/></svg>
                        @if ($count)
                            <span class="absolute -right-1.5 -top-1.5 inline-flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-negative px-1 text-[10px] font-bold leading-none text-white ring-2 ring-surface">{{ $count }}</span>
                        @endif
                    </span>
                    @break
                @case('trip')
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
                    @break
                @case('report')
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M7 3h7l4 4v14H7zM14 3v4h4"/><path d="M9.5 12h5M9.5 15.5h5" stroke-linecap="round"/></svg>
                    @break
            @endswitch
            <span class="text-[10.5px] font-semibold">{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
