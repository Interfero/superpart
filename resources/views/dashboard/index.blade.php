@extends('layouts.app')

@section('title', 'Главная — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => auth()->user()->isManager() ? 'Партнёр' : 'Главная', 'url' => null],
    ]" />
@endsection

@section('content')
    @php
        $isManager = auth()->user()->isManager();
        $homeTitle = $isManager ? 'Партнёр' : 'Главная';
    @endphp

    <div class="space-y-6">
        <x-page-hero>
            <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-foreground">{{ $homeTitle }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                        @if ($isManager)
                            Заявки и справочники партнёра.
                        @else
                            Заработок, выплаты и список заявок.
                        @endif
                    </p>
                </div>

                @unless ($isManager)
                    <div class="flex flex-wrap items-center gap-3 self-start lg:ml-auto">
                        @can('use-withdrawals')
                            <a href="{{ route('withdrawals.create') }}">
                                <x-ui.button variant="secondary">Заявка на выплату</x-ui.button>
                            </a>
                        @endcan
                        <a href="{{ route('transactions.index') }}">
                            <x-ui.button variant="secondary">Начисления</x-ui.button>
                        </a>
                    </div>
                @endunless
            </div>
        </x-page-hero>

        @unless ($isManager)
            <x-ui.card padding="none" class="overflow-hidden">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">Заработок</h2>
                </div>
                <div class="grid grid-cols-1 divide-y divide-border sm:grid-cols-2 lg:grid-cols-5 lg:divide-x lg:divide-y-0">
                    @foreach ($financeStats as $period)
                        <div class="min-w-0 px-4 py-4 text-center sm:px-6">
                            <div class="mb-2 break-words text-xs uppercase tracking-wider text-muted-foreground">
                                {{ $period['label'] }}
                            </div>
                            <div class="break-words text-lg font-bold tabular-nums text-foreground sm:text-xl">
                                {{ number_format($period['amount'], 0, ',', ' ') }} ₽
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            @if ($withdrawalStats)
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.card>
                        <div class="text-sm text-muted-foreground">
                            @if (! empty($withdrawalStats['is_elevated']))
                                Балансы партнёров (сумма)
                            @elseif (! empty($withdrawalStats['is_shared_pool']))
                                Баланс (общий)
                            @else
                                Баланс
                            @endif
                        </div>
                        <div class="mt-2 text-3xl font-bold text-foreground">
                            {{ number_format($withdrawalStats['balance'], 0, ',', ' ') }} ₽
                        </div>
                    </x-ui.card>
                    <x-ui.card>
                        <div class="text-sm text-muted-foreground">Доступно к выводу</div>
                        <div class="mt-2 text-3xl font-bold text-primary">
                            {{ number_format($withdrawalStats['available'], 0, ',', ' ') }} ₽
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">После периода охлаждения 36 часов</div>
                    </x-ui.card>
                    <x-ui.card>
                        <div class="text-sm text-muted-foreground">Выплаты на рассмотрении</div>
                        <div class="mt-2 text-3xl font-bold text-amber-500">
                            {{ number_format($withdrawalStats['in_work_amount'], 0, ',', ' ') }} ₽
                        </div>
                    </x-ui.card>
                    <x-ui.card>
                        <div class="text-sm text-muted-foreground">
                            @if (! empty($withdrawalStats['is_elevated']))
                                Выплаты партнёрам с 1 числа
                            @elseif (! empty($withdrawalStats['is_shared_pool']))
                                Выплачено с 1 числа (общее)
                            @else
                                Выплачено с 1 числа месяца
                            @endif
                        </div>
                        <div class="mt-2 text-3xl font-bold text-green-500">
                            {{ number_format($withdrawalStats['completed_month'], 0, ',', ' ') }} ₽
                        </div>
                    </x-ui.card>
                </div>
            @endif
        @endunless

        <div>
            <h2 class="mb-4 text-3xl font-bold text-foreground">Список заявок</h2>
            <p class="mb-3 text-sm text-muted-foreground">
                Выгрузка в Excel — в
                <a href="{{ route('reports.orders') }}" class="text-primary underline-offset-2 hover:underline">отчёте по заявкам</a>
                (там можно выбрать период и фильтры).
            </p>

            @include('orders._list-panel', [
                'formAction' => route('home'),
                'tabsUrl' => route('home'),
                // Экспорт без дат — на отчёте по заявкам (там есть период).
                'showExport' => false,
                'showCreateButton' => true,
                'filtersAlwaysVisible' => true,
            ])
        </div>
    </div>
@endsection
