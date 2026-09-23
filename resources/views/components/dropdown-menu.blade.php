@props(['title', 'items'])

<div class="dropdown-menu relative" data-dropdown>
    <button type="button"
            class="flex items-center gap-1 px-3 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
            data-dropdown-toggle>
        {{ $title }}
        <svg class="w-3.5 h-3.5 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="dropdown-panel absolute left-0 top-full mt-0 min-w-[200px] bg-card rounded-md shadow-xl border border-border opacity-0 invisible transition-all duration-200 z-50"
         data-dropdown-panel>
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}"
               class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors first:rounded-t-md last:rounded-b-md">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</div>
