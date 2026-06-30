<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-canvas">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Forvalter' }}</title>

        {{-- PWA --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#ffffff">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Forvalter">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/icon-192.png">
        @include('partials.pwa-splash')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        {{-- Pull-to-refresh indicator (installed iOS PWA only; wired up below) --}}
        <div id="ptr" aria-hidden="true" style="position:fixed;top:0;left:0;right:0;z-index:40;display:flex;justify-content:center;pointer-events:none;transform:translateY(-46px);">
            <div id="ptr-ic" style="margin-top:calc(env(safe-area-inset-top) + 8px);height:32px;width:32px;border-radius:9999px;background:#fff;box-shadow:0 2px 10px rgba(22,24,29,.14);display:flex;align-items:center;justify-content:center;color:#2c5ce6;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            </div>
        </div>
        @php
            $intakeCount = auth()->check()
                ? \App\Models\DocumentAnalysis::whereIn('status', ['pending', 'draft'])->count()
                : 0;
        @endphp

        <div
            x-data="bilagDrop({{ Illuminate\Support\Js::from(route('intake.ingest')) }}, {{ Illuminate\Support\Js::from(route('intake')) }})"
            @dragenter.window="onDragEnter($event)"
            @dragover.window.prevent
            @dragleave.window="onDragLeave($event)"
            @drop.window.prevent="onDrop($event)"
        >
            {{-- Global full-page drop overlay --}}
            <div x-show="dragging" x-cloak x-transition.opacity.duration.150ms
                class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-6 backdrop-blur-[2px]">
                <div class="flex flex-col items-center gap-3 rounded-3xl border-2 border-dashed border-white/80 bg-canvas/95 px-14 py-12 text-center shadow-2xl">
                    <svg class="size-9 text-terra" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16V4M12 4l-4 4M12 4l4 4M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/>
                    </svg>
                    <div class="text-lg font-semibold">Slipp bilaget her</div>
                    <div class="text-sm text-muted">PDF, JPG eller PNG · analyseres automatisk</div>
                </div>
            </div>

            {{-- Toasts (drop feedback) --}}
            <div class="fixed inset-x-0 bottom-24 z-50 flex flex-col items-center gap-2 px-5 md:inset-x-auto md:bottom-6 md:right-6 md:items-end" x-cloak>
                <template x-for="t in toasts" :key="t.id">
                    <a :href="t.link ? inboxUrl : null" x-transition
                        class="flex max-w-sm items-center gap-2.5 rounded-xl border border-line bg-surface px-4 py-3 text-sm font-medium shadow-lg"
                        :class="t.link ? 'cursor-pointer text-ink hover:border-faint' : 'text-muted'">
                        <span x-show="!t.done" class="size-2 shrink-0 animate-pulse rounded-full bg-terra"></span>
                        <span x-show="t.done" class="shrink-0 text-terra">✨</span>
                        <span x-text="t.message"></span>
                    </a>
                </template>
            </div>

            {{-- Desktop top navigation --}}
            <header class="sticky top-0 z-10 hidden border-b border-line md:block" style="background:rgba(255,255,255,.82); backdrop-filter:blur(10px);">
                <div class="mx-auto flex h-16 max-w-[1280px] items-center gap-5 px-8">
                    <x-brand />
                    <div class="h-[22px] w-px bg-line-strong"></div>

                    <nav class="flex items-center gap-1">
                        <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">Oversikt</x-nav-link>
                        <x-nav-link href="{{ route('properties.index') }}" :active="request()->routeIs('properties.*') || request()->routeIs('units.*')">Boliger</x-nav-link>
                        <x-nav-link href="{{ route('intake') }}" :active="request()->routeIs('intake')">
                            <span class="inline-flex items-center gap-1.5">
                                Innboks
                                @if ($intakeCount)
                                    <span class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-terra px-1 text-[11px] font-semibold leading-none text-white">{{ $intakeCount }}</span>
                                @endif
                            </span>
                        </x-nav-link>
                        <x-nav-link href="{{ route('trips.index') }}" :active="request()->routeIs('trips.*')">Kjørebok</x-nav-link>
                        <x-nav-link href="{{ route('arsoppgjor') }}" :active="request()->routeIs('arsoppgjor')">Årsoppgjør</x-nav-link>
                    </nav>

                    <div class="ml-auto flex items-center gap-3">
                        <a href="{{ route('expenses.create') }}"
                            class="inline-flex items-center gap-1.5 rounded-[9px] bg-terra py-2.5 pl-3 pr-4 text-sm font-semibold text-white transition-opacity hover:opacity-90">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            Ny utgift
                        </a>
                        <div class="h-[22px] w-px bg-line-strong"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-muted transition-colors hover:text-ink">Logg ut</button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- Mobile top bar --}}
            <header class="sticky top-0 z-10 flex h-14 items-center justify-between border-b border-line bg-canvas px-5 md:hidden">
                <x-brand />
                <div class="flex items-center gap-4">
                    <a href="{{ route('intake') }}" aria-label="Innboks"
                        class="relative {{ request()->routeIs('intake') ? 'text-terra' : 'text-muted' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M4 13h4l1.5 2.5h5L16 13h4M4 13l2-7h12l2 7M4 13v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/></svg>
                        @if ($intakeCount)
                            <span class="absolute -right-2 -top-1.5 inline-flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-terra px-1 text-[10px] font-bold leading-none text-white">{{ $intakeCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('arsoppgjor') }}" aria-label="Årsoppgjør"
                        class="{{ request()->routeIs('arsoppgjor') ? 'text-terra' : 'text-muted' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M7 3h7l4 4v14H7zM14 3v4h4"/><path d="M9.5 12h5M9.5 15.5h5" stroke-linecap="round"/></svg>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-[13px] text-muted">Logg ut</button>
                    </form>
                </div>
            </header>

            <main class="mx-auto max-w-[1280px] px-5 pb-28 pt-8 md:px-8 md:pb-20 md:pt-10">
                {{ $slot }}
            </main>

            <x-bottom-nav :count="$intakeCount" />
        </div>

        @livewireScripts

        <script>
            // Register the PWA service worker (installable + fast launch).
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js').catch(() => {});
                });
            }

            // Pull-to-refresh — only in the installed (standalone) app, where iOS
            // has no native pull-to-refresh. Drag down from the very top to reload.
            (function () {
                const standalone = window.navigator.standalone === true
                    || window.matchMedia('(display-mode: standalone)').matches;
                if (!standalone) return;

                const el = document.getElementById('ptr');
                const ic = document.getElementById('ptr-ic');
                if (!el || !ic) return;

                const THRESHOLD = 64, MAX = 108, REST = -46;
                let startY = null, dist = 0, pulling = false;
                const atTop = () => (window.scrollY || document.documentElement.scrollTop || 0) <= 0;

                // Don't arm while a modal/lightbox overlay is open (avoids a
                // reload that would discard what you're editing).
                const overlayOpen = () => [...document.querySelectorAll('.z-50')]
                    .some((m) => m.offsetParent !== null && m.getBoundingClientRect().height > 60);

                document.addEventListener('touchstart', (e) => {
                    if (e.touches.length !== 1 || !atTop() || overlayOpen()) { startY = null; return; }
                    startY = e.touches[0].clientY;
                    dist = 0; pulling = false;
                    el.style.transition = 'none';
                    ic.style.transition = 'none';
                }, { passive: true });

                document.addEventListener('touchmove', (e) => {
                    if (startY === null) return;
                    const dy = e.touches[0].clientY - startY;
                    if (dy <= 0 || !atTop()) { if (!pulling) startY = null; return; }
                    pulling = true;
                    dist = Math.min(dy * 0.5, MAX);
                    el.style.transform = 'translateY(' + (REST + dist) + 'px)';
                    ic.style.transform = 'rotate(' + (dist / MAX * 320) + 'deg)';
                }, { passive: true });

                document.addEventListener('touchend', () => {
                    if (startY === null) return;
                    const trigger = pulling && dist >= THRESHOLD;
                    startY = null; pulling = false;
                    el.style.transition = 'transform .22s ease';
                    if (trigger) {
                        el.style.transform = 'translateY(10px)';
                        ic.classList.add('ptr-spinning');
                        setTimeout(() => window.location.reload(), 180);
                    } else {
                        el.style.transform = 'translateY(' + REST + 'px)';
                        ic.style.transition = 'transform .22s ease';
                        ic.style.transform = 'rotate(0deg)';
                    }
                });
            })();
        </script>

        <script>
            function bilagDrop(ingestUrl, inboxUrl) {
                return {
                    ingestUrl,
                    inboxUrl,
                    dragging: false,
                    depth: 0,
                    toasts: [],
                    seq: 0,

                    hasFiles(e) {
                        return e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
                    },
                    onDragEnter(e) {
                        if (!this.hasFiles(e)) return;
                        this.depth++;
                        this.dragging = true;
                    },
                    onDragLeave() {
                        this.depth--;
                        if (this.depth <= 0) { this.depth = 0; this.dragging = false; }
                    },
                    accepted(f) {
                        return /^application\/pdf$/.test(f.type)
                            || /^image\/(jpeg|png)$/.test(f.type)
                            || /\.(pdf|jpe?g|png)$/i.test(f.name);
                    },
                    onDrop(e) {
                        this.depth = 0;
                        this.dragging = false;
                        this.upload(Array.from(e.dataTransfer?.files || []));
                    },
                    // Shared by drag-drop AND the mobile "snap / upload" file inputs.
                    async upload(fileList) {
                        const files = Array.from(fileList || []).filter(f => this.accepted(f));
                        if (!files.length) return;

                        const fd = new FormData();
                        files.forEach(f => fd.append('files[]', f));

                        const upId = this.push(files.length === 1 ? 'Laster opp bilag …' : files.length + ' bilag lastes opp …', false, false);
                        try {
                            const res = await fetch(this.ingestUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: fd,
                            });
                            if (!res.ok) throw new Error('upload failed');
                            const data = await res.json();
                            this.remove(upId);
                            const msg = (data.count === 1 ? 'Bilaget analyseres' : data.count + ' bilag analyseres') + ' — åpne innboks';
                            this.push(msg, true, true);
                            if (window.Livewire) window.Livewire.dispatch('bilag-mottatt');
                        } catch (err) {
                            this.remove(upId);
                            this.push('Kunne ikke laste opp bilaget. Prøv igjen.', true, false);
                        }
                    },
                    push(message, done, link) {
                        const id = ++this.seq;
                        this.toasts.push({ id, message, done, link });
                        if (this.toasts.length > 3) this.toasts = this.toasts.slice(-3);
                        if (done) setTimeout(() => this.remove(id), 7000);
                        return id;
                    },
                    remove(id) {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    },
                };
            }
        </script>
    </body>
</html>
