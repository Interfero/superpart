@extends('layouts.app')

@section('title', "Заявка №{$order->id} — SuperPart")

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Список заявок', 'url' => route('orders.index')],
        ['label' => 'Заявка №' . $order->id, 'url' => null],
    ]" />
@endsection

@section('content')

@php

    $typeLabels = [
        'first_time' => 'Впервые',
        'first' => 'Впервые',
        'warranty' => 'Гарантия',
        'repeat' => 'Повтор',
    ];

    $serverLabels = \App\Models\Order::serverTypeLabels();

    $sourceName = $order->referenceSource->name
        ?? $order->source->name
        ?? '—';

    $addressParts = array_filter([
        $order->street,
        $order->house ? 'д. ' . $order->house : null,
        $order->flat ? 'кв/офис ' . $order->flat : null,
    ]);

    $address = $addressParts
        ? implode(', ', $addressParts)
        : ($order->address ?: '—');

@endphp

<div class="space-y-6">

    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">

        <div class="flex flex-col gap-6 px-6 py-7 xl:flex-row xl:items-start xl:justify-between">

            <div>

                <div class="flex flex-wrap items-center gap-3">

                    <h1 class="text-3xl font-bold text-foreground">
                        Заявка №{{ $order->id }}
                    </h1>

                    <x-status-badge :status="$order->status" type="order" />

                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">

                    <div>
                        Создано:
                        <span class="text-foreground">
                            {{ $order->created_local?->format('d.m.Y H:i') ?? '—' }}
                        </span>
                    </div>

                    @if ($order->closed_local)

                        <div>
                            Закрыто:
                            <span class="text-foreground">
                                {{ $order->closed_local->format('d.m.Y H:i') }}
                            </span>
                        </div>

                    @endif

                    <div>
                        Город:
                        <span class="text-foreground">
                            {{ $order->city->name ?? '—' }}
                        </span>
                    </div>

                </div>

            </div>

            <div class="flex flex-wrap gap-3">

                <x-ui.button
                    tag="a"
                    :href="route('orders.index')"
                    variant="secondary"
                >
                    Назад
                </x-ui.button>

            </div>

        </div>

    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Начисление
            </div>

            <div class="mt-2 text-3xl font-bold text-primary">

                @if ($order->charge_amount > 0)

                    {{ number_format($order->charge_amount, 0, ',', ' ') }} ₽

                @else

                    —

                @endif

            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Тип заявки
            </div>

            <div class="mt-2 text-xl font-semibold text-foreground">
                {{ $typeLabels[$order->type] ?? $order->type ?? '—' }}
            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Источник
            </div>

            <div class="mt-2 text-xl font-semibold text-foreground">
                {{ $sourceName }}
            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Сотрудник
            </div>

            <div class="mt-2 text-xl font-semibold text-foreground">
                {{ $order->creatorDisplayName() ?: '—' }}
            </div>

        </x-ui.card>

    </div>

    <div class="grid gap-6 xl:grid-cols-3">

        <div class="space-y-6 xl:col-span-2">

            <x-ui.card>

                <h2 class="mb-5 text-lg font-semibold text-foreground">
                    Информация по заявке
                </h2>

                <div class="grid gap-5 md:grid-cols-2">

                    <div class="space-y-4">

                        <div>
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                Сервер
                            </div>

                            <div class="mt-1 text-foreground">
                                {{ $serverLabels[$order->server_type] ?? $order->server_type ?? '—' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                Профильность
                            </div>

                            <div class="mt-1 text-foreground">
                                {{ $order->is_non_profile ? 'Непрофильный' : 'Профильный' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                Вид работ
                            </div>

                            <div class="mt-1 text-foreground">
                                {{ $order->workType->name ?? $order->equipment_type ?? '—' }}
                            </div>
                        </div>

                    </div>

                    <div class="space-y-4">

                        <div>
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                Время визита
                            </div>

                            <div class="mt-1 text-foreground">
                                {{ $order->order_time?->format('d.m.Y H:i') ?? '—' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                Без звонка
                            </div>

                            <div class="mt-1 text-foreground">
                                {{ $order->without_call ? 'Да' : 'Нет' }}
                            </div>
                        </div>

                    </div>

                </div>

            </x-ui.card>

            <x-ui.card>

                <h2 class="mb-5 text-lg font-semibold text-foreground">
                    Клиент
                </h2>

                <div class="grid gap-5 md:grid-cols-3">

                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">
                            Имя
                        </div>

                        <div class="mt-1 text-foreground">
                            {{ $order->client_name ?? '—' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">
                            Телефон
                        </div>

                        <div class="mt-1 text-foreground">
                            {{ $order->client_phone ?? '—' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">
                            Возраст
                        </div>

                        <div class="mt-1 text-foreground">
                            {{ $order->client_age ?? '—' }}
                        </div>
                    </div>

                </div>

            </x-ui.card>

            <x-ui.card>

                <h2 class="mb-5 text-lg font-semibold text-foreground">
                    Адрес
                </h2>

                <div class="space-y-4">

                    <div>

                        <div class="text-xs uppercase tracking-wide text-muted-foreground">
                            Адрес клиента
                        </div>

                        <div class="mt-1 text-foreground">
                            {{ $address }}
                        </div>

                    </div>

                    @if ($order->address_adds)

                        <div>

                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                Комментарий
                            </div>

                            <div class="mt-1 whitespace-pre-line text-foreground">
                                {{ $order->address_adds }}
                            </div>

                        </div>

                    @endif

                </div>

            </x-ui.card>

            <x-ui.card>

                <h2 class="mb-5 text-lg font-semibold text-foreground">
                    Описание заказа
                </h2>

                <div class="whitespace-pre-line text-sm leading-7 text-foreground">
                    {{ $order->order_adds ?: 'Описание отсутствует.' }}
                </div>

            </x-ui.card>

        </div>

        <div class="space-y-6">

            <x-ui.card class="sticky top-6">

                <h2 class="mb-5 text-lg font-semibold text-foreground">
                    Быстрая информация
                </h2>

                <div class="space-y-4">

                    <div class="flex items-center justify-between gap-3">

                        <span class="text-sm text-muted-foreground">
                            ID
                        </span>

                        <span class="font-medium text-foreground">
                            #{{ $order->id }}
                        </span>

                    </div>

                    <div class="flex items-center justify-between gap-3">

                        <span class="text-sm text-muted-foreground">
                            Статус
                        </span>

                        <x-status-badge :status="$order->status" type="order" />

                    </div>

                    <div class="flex items-center justify-between gap-3">

                        <span class="text-sm text-muted-foreground">
                            Город
                        </span>

                        <span class="text-foreground">
                            {{ $order->city->name ?? '—' }}
                        </span>

                    </div>

                    <div class="flex items-center justify-between gap-3">

                        <span class="text-sm text-muted-foreground">
                            Источник
                        </span>

                        <span class="text-foreground">
                            {{ $sourceName }}
                        </span>

                    </div>

                </div>

            </x-ui.card>

        </div>

    </div>

</div>

@endsection