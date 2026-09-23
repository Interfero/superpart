@props([
    'name' => 'client_phone',
    'id' => null,
    'value' => '',
    'required' => false,
    'label' => null,
    'tip' => null,
    'error' => false,
])

@php
    $fieldId = $id ?? $name;
@endphp
<div class="phone-input-ru-wrapper w-full relative has-[.field-tip:hover]:z-[200] has-[.field-tip:focus-within]:z-[200]">
    @if ($label)
        <div class="mb-1 flex items-center gap-0.5 flex-wrap overflow-visible">
            <x-ui.label :for="$fieldId" :required="$required">{{ $label }}</x-ui.label>
            @if ($tip)
                @includeWhen(
                    view()->exists('components.ui.field-tip'),
                    'components.ui.field-tip',
                    ['text' => $tip]
                )
            @endif
        </div>
    @endif
    <div {{ $attributes->class(['phone-input-ru flex w-full min-w-0 items-stretch rounded-md border border-input bg-input transition-colors focus-within:border-muted-foreground/50 focus-within:ring-0']) }}>
        <span class="phone-input-ru-prefix inline-flex items-center px-3 text-sm text-muted-foreground border-r border-border shrink-0">+7</span>
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            autocomplete="tel-national"
            placeholder="9001234567"
            maxlength="10"
            inputmode="numeric"
            data-phone-mask="split"
            @class([
                'phone-input-ru-field flex-1 min-w-0 border-0 bg-transparent px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0',
                'ring-destructive/30 border-destructive' => $error,
            ])
        />
    </div>
</div>
