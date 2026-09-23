@props([
    'type' => 'text',
    'size' => 'sm',
    'error' => false,
    /** Узкое поле (например ID) без растягивания на всю ячейку */
    'narrow' => false,
])

@php
    $wrapperClass = $narrow
        ? 'relative w-full max-w-[6.5rem]'
        : 'relative';
@endphp

<div class="{{ $wrapperClass }}" data-filter-input>
    <x-ui.input
        :type="$type"
        :size="$size"
        :error="$error"
        autocomplete="off"
        {{ $attributes->class($narrow ? '!w-full min-w-0' : '') }}
    />
</div>
