@props(['amount', 'href' => null])

@php
    $classes = 'inline-flex items-center px-3 py-1.5 rounded-md text-primary text-sm font-medium bg-muted/30 transition-colors';
    if ($href) {
        $classes .= ' hover:bg-primary/15 hover:text-primary cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-ring';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} title="Перейти к выводу средств">
        Баланс: {{ number_format($amount, 0, '.', ' ') }} Р
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        Баланс: {{ number_format($amount, 0, '.', ' ') }} Р
    </div>
@endif
