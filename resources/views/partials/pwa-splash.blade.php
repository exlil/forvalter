{{-- iOS PWA launch images. One per device resolution; iOS picks the match. --}}
@php
    $splashes = [
        [375, 667, 2], [414, 736, 3], [375, 812, 3], [414, 896, 2], [414, 896, 3],
        [390, 844, 3], [393, 852, 3], [428, 926, 3], [430, 932, 3], [402, 874, 3],
        [440, 956, 3],
    ];
@endphp
@foreach ($splashes as [$w, $h, $r])
    <link rel="apple-touch-startup-image"
        media="(device-width: {{ $w }}px) and (device-height: {{ $h }}px) and (-webkit-device-pixel-ratio: {{ $r }}) and (orientation: portrait)"
        href="/splash/splash-{{ $w * $r }}x{{ $h * $r }}.png">
@endforeach
