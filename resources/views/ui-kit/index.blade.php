@extends('layouts.app')

@section('title', 'UI Kit — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'UI Kit', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="space-y-10">
        <div>
            <h1 class="text-2xl font-bold text-foreground mb-2">Витрина дизайн-системы</h1>
            <p class="text-muted-foreground text-sm mb-4">
                Локальная страница для проверки токенов и компонентов. Документация: <code class="text-primary">docs/DESIGN_SYSTEM.md</code>
            </p>
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="outline" size="sm" id="ui-kit-theme-toggle">
                    Переключить светлую/тёмную тему
                </x-ui.button>
            </div>
        </div>

        {{-- Палитра --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Семантические цвета</h2>
            @php
                $swatches = [
                    ['token' => 'background', 'class' => 'bg-background'],
                    ['token' => 'foreground', 'class' => 'bg-foreground'],
                    ['token' => 'card', 'class' => 'bg-card'],
                    ['token' => 'primary', 'class' => 'bg-primary'],
                    ['token' => 'secondary', 'class' => 'bg-secondary'],
                    ['token' => 'muted', 'class' => 'bg-muted'],
                    ['token' => 'accent', 'class' => 'bg-accent'],
                    ['token' => 'destructive', 'class' => 'bg-destructive'],
                    ['token' => 'border', 'class' => 'bg-border'],
                    ['token' => 'input', 'class' => 'bg-input'],
                    ['token' => 'ring', 'class' => 'bg-ring'],
                    ['token' => 'chart-1', 'class' => 'bg-chart-1'],
                    ['token' => 'chart-2', 'class' => 'bg-chart-2'],
                    ['token' => 'chart-3', 'class' => 'bg-chart-3'],
                    ['token' => 'chart-4', 'class' => 'bg-chart-4'],
                    ['token' => 'chart-5', 'class' => 'bg-chart-5'],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                @foreach ($swatches as $sw)
                    <div class="text-center">
                        <div class="h-14 rounded-md border border-border shadow-sm mb-1 {{ $sw['class'] }}"></div>
                        <div class="text-xs text-muted-foreground">{{ $sw['token'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Типографика --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Типографика</h2>
            <p class="text-xs text-foreground mb-1">text-xs</p>
            <p class="text-sm text-foreground mb-1">text-sm</p>
            <p class="text-base text-foreground mb-1">text-base</p>
            <p class="text-lg font-semibold text-foreground mb-1">text-lg semibold</p>
            <p class="text-2xl font-bold text-foreground mb-1">text-2xl bold</p>
            <p class="font-mono text-sm text-muted-foreground">font-mono JetBrains Mono</p>
        </x-ui.card>

        {{-- Кнопки --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Кнопки</h2>
            <div class="flex flex-wrap gap-2 items-center">
                <x-ui.button variant="primary" size="sm">Primary sm</x-ui.button>
                <x-ui.button variant="primary" size="md">Primary md</x-ui.button>
                <x-ui.button variant="primary" size="lg">Primary lg</x-ui.button>
                <x-ui.button variant="secondary" size="md">Secondary</x-ui.button>
                <x-ui.button variant="outline" size="md">Outline</x-ui.button>
                <x-ui.button variant="ghost" size="md">Ghost</x-ui.button>
                <x-ui.button variant="destructive" size="md">Destructive</x-ui.button>
                <x-ui.button variant="primary" size="icon" title="Иконка">⚙</x-ui.button>
                <x-ui.button variant="primary" size="md" disabled>Disabled</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Поля --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Поля ввода</h2>
            <div class="grid md:grid-cols-2 gap-6 max-w-3xl">
                <x-ui.form-group label="Текст (md)" name="demo_text">
                    <x-ui.input name="demo_text" placeholder="Placeholder" />
                </x-ui.form-group>
                <x-ui.form-group label="Маленький (sm)" name="demo_sm">
                    <x-ui.input size="sm" name="demo_sm" placeholder="filter" />
                </x-ui.form-group>
                <x-ui.form-group label="Селект" name="demo_sel">
                    <x-ui.select name="demo_sel">
                        <option value="">—</option>
                        <option value="1">Один</option>
                    </x-ui.select>
                </x-ui.form-group>
                <x-ui.form-group label="Текстовое поле" name="demo_area">
                    <x-ui.textarea name="demo_area" rows="3">Пример</x-ui.textarea>
                </x-ui.form-group>
                <div class="md:col-span-2">
                    <x-ui.checkbox name="demo_cb" label="Чекбокс с подписью" />
                </div>
            </div>
        </x-ui.card>

        {{-- Алерты --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Алерты</h2>
            <div class="space-y-2">
                <x-ui.alert type="success" class="!mb-2">Успех: операция выполнена.</x-ui.alert>
                <x-ui.alert type="error" class="!mb-2">Ошибка: проверьте поля.</x-ui.alert>
                <x-ui.alert type="warning" class="!mb-2">Предупреждение.</x-ui.alert>
                <x-ui.alert type="info">Информация.</x-ui.alert>
            </div>
        </x-ui.card>

        {{-- Карточки --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Карточки (padding)</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <x-ui.card padding="none" class="p-3 text-sm text-muted-foreground">none + p-3</x-ui.card>
                <x-ui.card padding="sm" class="text-sm text-muted-foreground">sm</x-ui.card>
                <x-ui.card padding="lg" :shadow="true" class="text-sm text-muted-foreground">lg + shadow</x-ui.card>
            </div>
        </x-ui.card>

        {{-- Бейджи статусов --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Бейджи статусов</h2>
            <div class="flex flex-wrap gap-2 items-center">
                <x-status-badge status="in_work" type="order" />
                <x-status-badge status="charge" type="transaction" />
                <x-status-badge status="completed" type="withdrawal" />
                <x-status-badge status="new" type="review" />
                <x-status-badge status="active" type="employee" />
            </div>
        </x-ui.card>

        {{-- Таблица data-table --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Таблица (x-data-table)</h2>
            @php
                $uiKitPage = (int) request('ui_page', 1);
                $uiKitAllRows = collect(range(1, 18))->map(fn ($num) => [
                    'id' => 100 + $num,
                    'name' => 'Строка ' . $num,
                ]);
                $uiKitPerPage = 5;
                $uiKitRows = $uiKitAllRows->forPage($uiKitPage, $uiKitPerPage)->values()->all();
                $uiKitPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    $uiKitRows,
                    $uiKitAllRows->count(),
                    $uiKitPerPage,
                    $uiKitPage,
                    [
                        'path' => url()->current(),
                        'pageName' => 'ui_page',
                        'query' => request()->except('ui_page'),
                    ]
                );

                $demoColumns = [
                    ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'filterable' => true],
                    ['key' => 'name', 'label' => 'Название', 'filterable' => true],
                ];
            @endphp
            <x-data-table :columns="$demoColumns" :rows="$uiKitRows" :paginator="$uiKitPaginator" :table-id="'ui-kit-table'" />
        </x-ui.card>

        {{-- Прочие компоненты --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Навигация и виджеты</h2>
            <div class="space-y-4">
                <x-breadcrumbs :items="[
                    ['label' => 'Главная', 'url' => '/'],
                    ['label' => 'Пример', 'url' => null],
                ]" />
                <x-balance-badge :amount="125000" />
                <x-tabs
                    :tabs="[
                        ['key' => 'a', 'label' => 'Вкладка A', 'count' => 3],
                        ['key' => 'b', 'label' => 'Вкладка B', 'count' => 1],
                    ]"
                    active="a"
                    url="/ui-kit"
                />
            </div>
        </x-ui.card>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('ui-kit-theme-toggle');
    const root = document.documentElement;
    btn?.addEventListener('click', () => {
        root.classList.toggle('dark');
        try {
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        } catch (e) {}
    });
});
</script>
@endpush
