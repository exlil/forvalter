<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-canvas-alt">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Forvalter' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-screen items-center justify-center bg-canvas-alt px-5 font-sans text-ink antialiased">
        <div class="w-full max-w-[400px]">
            {{ $slot }}
        </div>
        @livewireScripts
    </body>
</html>
