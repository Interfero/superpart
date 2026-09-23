@props(['tabs', 'active' => null, 'url' => ''])

<div class="flex items-center gap-6 overflow-x-auto pb-1 border-b border-border">
    @foreach ($tabs as $tabKey => $tab)
        @php
            if (! is_array($tab)) {
                $tab = [
                    'key' => is_string($tabKey) ? $tabKey : (string) $tabKey,
                    'label' => (string) $tab,
                ];
            }

            $tabKeyValue = (string) ($tab['key'] ?? (is_string($tabKey) ? $tabKey : $tabKey));
            $tabLabel = (string) ($tab['label'] ?? $tabKeyValue);
            $isActive = (string) $active === $tabKeyValue;
            $href = $url.'?'.http_build_query(array_merge(request()->except('status', 'page'), ['status' => $tabKeyValue]));
        @endphp
        <a href="{{ $href }}"
           class="flex items-center gap-1.5 pb-2 text-sm whitespace-nowrap border-b-2 transition-colors {{ $isActive ? 'border-primary text-foreground font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            <span>{{ $tabLabel }}</span>
            @if (isset($tab['count']))
                <span class="text-xs {{ $isActive ? 'text-muted-foreground' : 'text-muted-foreground/80' }}">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach
</div>
