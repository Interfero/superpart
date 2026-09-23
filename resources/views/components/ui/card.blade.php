@props([
    'padding' => 'md',
    'shadow' => false,
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8',
    ];

    $base = 'bg-card rounded-lg border border-border';
    $p = $paddingClasses[$padding] ?? $paddingClasses['md'];
    $shadowClass = $shadow ? ' shadow-sm dark:shadow-xl' : '';
@endphp

<div {{ $attributes->merge(['class' => $base . ' ' . $p . $shadowClass]) }}>
    {{ $slot }}
</div>
