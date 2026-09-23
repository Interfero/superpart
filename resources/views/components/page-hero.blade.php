{{-- Шапка страницы без рамки (рамка выглядела как случайное выделение) --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent']) }}>
    {{ $slot }}
</div>
