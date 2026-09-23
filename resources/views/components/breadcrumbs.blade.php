@props(['items'])

<nav class="flex items-center text-sm">
    @foreach ($items as $index => $item)
        @if ($index > 0)
            <span class="mx-2 text-muted-foreground">/</span>
        @endif

        @if ($item['url'] && $index < count($items) - 1)
            <a href="{{ $item['url'] }}" class="text-muted-foreground hover:text-foreground transition-colors">
                {{ $item['label'] }}
            </a>
        @else
            <span class="text-foreground font-semibold">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
