@props([
    'label' => '',
    'name' => '',
    'required' => false,
    'hint' => null,
    'tip' => null,
    'id' => null,
])

@php
    $controlId = $id ?? str_replace(['[', ']'], ['_', ''], $name);
    $tipText = $tip ?? $hint;
@endphp

<div {{ $attributes->except('id')->class('mb-4 relative has-[.field-tip:hover]:z-[200] has-[.field-tip:focus-within]:z-[200]') }}>
    @if ($label !== '')
        <div class="mb-1 flex items-center gap-0.5 flex-wrap overflow-visible">
            <x-ui.label :for="$controlId" :required="$required">{{ $label }}</x-ui.label>
            @if ($tipText)
                @includeWhen(
                    view()->exists('components.ui.field-tip'),
                    'components.ui.field-tip',
                    ['text' => $tipText]
                )
            @endif
        </div>
    @endif

    {{ $slot }}

    @error($name)
        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
    @enderror
</div>
