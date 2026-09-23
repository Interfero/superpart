@props([
    'rows' => 4,
    'error' => false,
])

@php
    $base =
        'w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder-muted-foreground/70 transition-colors resize-y focus:border-border focus:ring-0 focus-visible:border-muted-foreground/50 focus-visible:ring-0';
    $errorClasses = $error
        ? ' border-destructive focus:border-destructive focus:ring-destructive'
        : '';
@endphp

<textarea
    rows="{{ $rows }}"
    {{ $attributes->merge(['class' => $base . $errorClasses]) }}
>{{ $slot }}</textarea>
