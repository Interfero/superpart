@props([
    'name',
    'id',
    'type' => 'date',
    'value' => '',
    'minDate' => null,
    'linkedDate' => null,
    'error' => false,
])

@php
    $display = $value;
    if ($type === 'date' && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', (string) $value, $m)) {
        $display = $m[1].'.'.$m[2].'.'.$m[3];
    }

    $triggerClasses = 'scroll-picker-trigger flex w-full items-center justify-between gap-2 rounded-md border border-border bg-input px-3 py-2 text-sm text-foreground outline-none transition-colors focus-visible:border-muted-foreground/50';
    if ($error) {
        $triggerClasses .= ' border-destructive';
    }
@endphp

@once
    @push('head')
        <style>
            .scroll-picker-frame { height: 180px; position: relative; }
            .scroll-picker-columns { display: flex; }
            .scroll-picker-column {
                flex: 1;
                height: 180px;
                overflow-y: auto;
                overscroll-behavior: contain;
                scroll-snap-type: y mandatory;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            .scroll-picker-column::-webkit-scrollbar { display: none; }
            .scroll-picker-highlight {
                position: absolute;
                left: 8px;
                right: 8px;
                top: 50%;
                z-index: 0;
                height: 36px;
                transform: translateY(-50%);
                border-radius: 0.375rem;
                border: 1px solid rgb(59 130 246 / 0.45);
                background: rgb(59 130 246 / 0.22);
                pointer-events: none;
            }
            .scroll-picker-item {
                width: 100%;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.875rem;
                line-height: 1;
                font-variant-numeric: tabular-nums;
                color: #94a3b8;
                scroll-snap-align: center;
                scroll-snap-stop: always;
                background: transparent;
                border: 0;
                cursor: pointer;
            }
            .scroll-picker-item:not([disabled]):hover { color: #f8fafc; }
            .scroll-picker-item.is-disabled,
            .scroll-picker-item:disabled { opacity: 0.25; cursor: not-allowed; }
        </style>
    @endpush
@endonce

<div
    class="scroll-picker relative"
    data-scroll-picker
    data-scroll-picker-type="{{ $type }}"
    @if ($minDate) data-min-date="{{ $minDate }}" @endif
    @if ($linkedDate) data-linked-date="{{ $linkedDate }}" @endif
    id="{{ $id }}_picker"
>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}">

    <button
        type="button"
        class="{{ $triggerClasses }}"
        data-scroll-picker-trigger
        aria-haspopup="dialog"
        aria-expanded="false"
        aria-controls="{{ $id }}_panel"
    >
        <span class="tabular-nums" data-scroll-picker-display>{{ $display ?: '—' }}</span>
        @if ($type === 'time')
            <svg class="h-4 w-4 shrink-0 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke-width="1.5"></circle>
                <path stroke-linecap="round" stroke-width="1.5" d="M12 7v5l3 2"></path>
            </svg>
        @else
            <svg class="h-4 w-4 shrink-0 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.5"></rect>
                <path stroke-linecap="round" stroke-width="1.5" d="M8 3v4M16 3v4M3 10h18"></path>
            </svg>
        @endif
    </button>

    <div
        id="{{ $id }}_panel"
        class="scroll-picker-panel absolute z-50 mt-1 hidden w-full min-w-[220px] overflow-hidden rounded-lg border border-border bg-card shadow-lg"
        data-scroll-picker-panel
        role="dialog"
        aria-modal="true"
    >
        @if ($type === 'date')
            <div class="grid grid-cols-3 gap-0 border-b border-border bg-muted/30 px-2 py-1.5 text-center text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                <span>День</span>
                <span>Месяц</span>
                <span>Год</span>
            </div>
        @else
            <div class="grid grid-cols-2 gap-0 border-b border-border bg-muted/30 px-2 py-1.5 text-center text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                <span>Часы</span>
                <span>Минуты</span>
            </div>
        @endif

        <div class="scroll-picker-frame relative">
            <div class="scroll-picker-highlight" aria-hidden="true"></div>
            <div class="scroll-picker-columns flex" data-scroll-picker-columns></div>
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-border px-3 py-2">
            <span class="text-xs tabular-nums text-muted-foreground" data-scroll-picker-preview></span>
            <div class="flex items-center gap-3">
                <button type="button" class="text-xs text-muted-foreground hover:text-foreground" data-scroll-picker-cancel>
                    Отмена
                </button>
                <button type="button" class="text-xs font-semibold text-primary hover:text-primary/80" data-scroll-picker-confirm>
                    Выбрать
                </button>
            </div>
        </div>
    </div>
</div>
