@php
    $faviconPath = public_path('favicon.png');
    $faviconV = file_exists($faviconPath) ? filemtime($faviconPath) : 1;
@endphp
@if (file_exists($faviconPath))
    <link rel="icon" href="{{ asset('favicon.png') }}?v={{ $faviconV }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}?v={{ $faviconV }}">
@endif
