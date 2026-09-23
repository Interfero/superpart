@props([
    'name',
    'id',
    'type' => 'date',
    'value' => '',
    'minDate' => null,
    'linkedDate' => null,
    'error' => false,
    'placeholder' => null,
])

@php
    $placeholder = $placeholder ?? ($type === 'date' ? 'дд.мм.гггг' : 'чч:мм');
    $inputClasses = 'min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-foreground tabular-nums outline-none placeholder:text-muted-foreground/60';
    $wrapClasses = 'flex w-full items-stretch overflow-hidden rounded-md border border-border bg-input transition-colors focus-within:border-muted-foreground/50';
    if ($error) {
        $wrapClasses .= ' border-destructive focus-within:border-destructive';
    }
@endphp

@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('css/datetime-picker.css') }}?v=20260605">
    @endpush
@endonce

<div
    class="datetime-field relative"
    data-datetime-field
    data-datetime-type="{{ $type }}"
    @if ($minDate) data-min-date="{{ $minDate }}" @endif
    @if ($linkedDate) data-linked-date="{{ $linkedDate }}" @endif
    id="{{ $id }}_picker"
>
    <div class="{{ $wrapClasses }}">
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            maxlength="{{ $type === 'date' ? 10 : 5 }}"
            autocomplete="off"
            inputmode="numeric"
            class="{{ $inputClasses }}"
            data-datetime-input
            @if ($type === 'date') aria-label="Дата" @else aria-label="Время" @endif
        >

        <button
            type="button"
            class="flex shrink-0 items-center justify-center border-l border-border px-3 text-muted-foreground transition-colors hover:bg-muted/30 hover:text-foreground"
            data-datetime-toggle
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-controls="{{ $id }}_panel"
            @if ($type === 'date') aria-label="Открыть календарь" @else aria-label="Открыть выбор времени" @endif
        >
            @if ($type === 'time')
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke-width="1.5"></circle>
                    <path stroke-linecap="round" stroke-width="1.5" d="M12 7v5l3 2"></path>
                </svg>
            @else
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.5"></rect>
                    <path stroke-linecap="round" stroke-width="1.5" d="M8 3v4M16 3v4M3 10h18"></path>
                </svg>
            @endif
        </button>
    </div>

    <div
        id="{{ $id }}_panel"
        class="datetime-panel absolute z-50 mt-1 hidden min-w-[280px] overflow-hidden rounded-lg border border-border bg-card shadow-xl"
        data-datetime-panel
        role="dialog"
        aria-modal="true"
    ></div>
</div>
