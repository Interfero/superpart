@props([
    'type' => 'text',
    'size' => 'md',
    'error' => false,
])

@php
    $sizeClasses = [
        'md' =>
            'w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder-muted-foreground/70 transition-colors focus:border-border focus:ring-0 focus-visible:border-muted-foreground/50 focus-visible:ring-0',
        'sm' =>
            'w-full bg-input border border-border text-foreground text-xs rounded px-2 py-1.5 outline-none placeholder-muted-foreground/70 transition-colors focus:border-border focus:ring-0 focus-visible:border-muted-foreground/50 focus-visible:ring-0',
    ];

    $errorClasses = $error
        ? ' border-destructive focus:border-destructive focus:ring-destructive'
        : '';

    $pickerClasses = in_array($type, ['date', 'time', 'datetime-local'], true)
        ? ' color-scheme-dark [color-scheme:dark]'
        : '';
@endphp

<input
    type="{{ $type }}"
    {{ $attributes->merge(['class' => ($sizeClasses[$size] ?? $sizeClasses['md']) . $errorClasses . $pickerClasses]) }}
/>
