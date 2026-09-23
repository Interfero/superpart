@props([
    'action' => '',
    'showClosedDates' => false,
    'showWorkType' => false,
    'dateFrom' => null,
    'dateTo' => null,
    'closedFrom' => null,
    'closedTo' => null,
    'workTypes' => [],
    'selectedWorkType' => null,
])

<form action="{{ $action }}" method="GET" class="flex flex-wrap items-end gap-4 mb-6" data-date-filter-form>
    <div>
        <label class="block text-xs text-muted-foreground mb-1">Дата от</label>
        <div class="relative">
            <x-ui.input
                type="date"
                name="date_from"
                value="{{ $dateFrom }}"
                class="date-filter-input pl-9 pr-8"
            />
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            @if ($dateFrom)
                <button type="button"
                        class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        data-target="date_from">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif
        </div>
    </div>

    <div>
        <label class="block text-xs text-muted-foreground mb-1">Дата по</label>
        <div class="relative">
            <x-ui.input
                type="date"
                name="date_to"
                value="{{ $dateTo }}"
                class="date-filter-input pl-9 pr-8"
            />
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            @if ($dateTo)
                <button type="button"
                        class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        data-target="date_to">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif
        </div>
    </div>

    @if ($showClosedDates)
        <div>
            <label class="block text-xs text-muted-foreground mb-1">Закрыто от</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="closed_from"
                    value="{{ $closedFrom }}"
                    class="date-filter-input pl-9 pr-8"
                />
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                @if ($closedFrom)
                    <button type="button"
                            class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            data-target="closed_from">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <div>
            <label class="block text-xs text-muted-foreground mb-1">Закрыто по</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="closed_to"
                    value="{{ $closedTo }}"
                    class="date-filter-input pl-9 pr-8"
                />
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                @if ($closedTo)
                    <button type="button"
                            class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            data-target="closed_to">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    @endif

    @if ($showWorkType)
        <div>
            <label class="block text-xs text-muted-foreground mb-1">Вид работ</label>
            <x-ui.select name="work_type" class="min-w-[160px]">
                <option value="">—</option>
                @foreach ($workTypes as $wt)
                    <option value="{{ $wt->id }}" @selected($selectedWorkType == $wt->id)>{{ $wt->name }}</option>
                @endforeach
            </x-ui.select>
        </div>
    @endif

    @php
        $dateErrors = isset($errors) ? $errors : new \Illuminate\Support\ViewErrorBag([]);
    @endphp
    @if ($dateErrors->has('date_to') || $dateErrors->has('closed_to'))
        <p class="w-full text-xs text-destructive -mt-2 mb-2">
            {{ $dateErrors->first('date_to') ?? $dateErrors->first('closed_to') }}
        </p>
    @endif

    {{ $slot }}

    <x-ui.button type="submit" variant="primary" size="icon" class="rounded-full shrink-0" title="Применить фильтр">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
        </svg>
    </x-ui.button>
</form>
