@props([
    'text' => '',
])

<span class="field-tip group relative inline-flex shrink-0 align-middle ml-1">
    <button
        type="button"
        tabindex="0"
        class="field-tip-trigger inline-flex h-4 w-4 items-center justify-center rounded-full border border-border bg-muted/40 text-[10px] font-semibold text-muted-foreground cursor-help focus:outline-none focus-visible:ring-1 focus-visible:ring-ring/40"
        aria-label="Подсказка"
        aria-describedby="{{ $tipId = 'field-tip-' . md5($text) }}"
    >i</button>
    <span
        id="{{ $tipId }}"
        role="tooltip"
        class="field-tip-popover pointer-events-none absolute z-[99999] hidden rounded-lg border border-border bg-popover px-3 py-2 text-left text-sm font-normal leading-relaxed text-popover-foreground shadow-xl"
        style="min-width: 14rem; max-width: min(18rem, calc(100vw - 2rem)); width: max-content; white-space: normal; overflow-wrap: break-word;"
    >{{ $text }}</span>
</span>
