@props(['title', 'buttonLabel' => null, 'buttonUrl' => null])

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-3xl font-bold text-foreground">{{ $title }}</h1>

    @if ($buttonLabel && $buttonUrl)
        <x-ui.button tag="a" :href="$buttonUrl" variant="primary" size="md">
            {{ $buttonLabel }}
        </x-ui.button>
    @endif
</div>
