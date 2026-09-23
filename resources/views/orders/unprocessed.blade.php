@extends('layouts.app')

@section('title', 'Не оформленные — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Не оформленные', 'url' => null],
    ]" />
@endsection

@section('content')

@php
    $totalOrders = $orders->total();
    $totalCharges = $orders->sum('charge_amount');

    $typeLabels = [
        'first_time' => 'Впервые',
        'first' => 'Впервые',
        'warranty' => 'Гарантия',
        'repeat' => 'Повтор',
    ];
@endphp

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500/15 via-amber-500/5 to-transparent mb-6">

    <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">

        <div>

            <h1 class="text-3xl font-bold text-foreground">
                Не оформленные заявки
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Заявки без оформления, требующие внимания сотрудников.
            </p>

        </div>

    </div>

</div>

<div class="grid gap-4 mb-6 md:grid-cols-2 xl:grid-cols-3">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Не оформлено
        </div>

        <div class="mt-2 text-3xl font-bold text-amber-500">
            {{ $totalOrders }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Начисления
        </div>

        <div class="mt-2 text-3xl font-bold text-primary">
            {{ number_format($totalCharges, 0, ',', ' ') }} ₽
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Страница
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ $orders->currentPage() }}
        </div>

    </x-ui.card>

</div>

<x-ui.card padding="none" class="overflow-hidden">

    <form method="GET" action="/orders/unprocessed" id="unprocessed-filter-form">

        @if (request('sort_by'))
            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
        @endif

        @if (request('sort_dir'))
            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
        @endif

        <div class="overflow-x-auto">

            <table class="table-sticky w-full text-sm text-left mt-2">

                <thead class="sticky top-0 z-10 bg-card">

                    <tr class="border-b border-border bg-card/95 backdrop-blur">

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap w-10">
                            ▼
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors" data-sort-key="id">
                            ID заявки
                        </th>

                        <th class="px-2 py-3 text-muted-foreground font-normal whitespace-nowrap w-8"></th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            НПр
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Город
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Статус
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Вид
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Адрес
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Источник
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Время заявки
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Имя
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Телефон
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Создано
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Начисление
                        </th>

                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Сотрудник
                        </th>

                    </tr>

                    <tr class="border-b border-border bg-muted/30">

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">
                            <x-ui.filter-input
                                type="text"
                                name="filter_id"
                                value="{{ request('filter_id') }}"
                                size="sm"
                            />
                        </td>

                        <td class="px-2 py-2"></td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_city" size="sm">

                                <option value="">—</option>

                                @foreach ($filterOptions['cities'] as $cityId => $cityName)

                                    <option value="{{ $cityId }}"
                                        {{ request('filter_city') == $cityId ? 'selected' : '' }}>
                                        {{ $cityName }}
                                    </option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_status" size="sm">

                                <option value="">—</option>

                                @foreach ($filterOptions['statuses'] as $statusKey => $statusLabel)

                                    <option value="{{ $statusKey }}"
                                        {{ request('filter_status') == $statusKey ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_type" size="sm">

                                <option value="">—</option>

                                @foreach ($filterOptions['types'] as $typeKey => $typeLabel)

                                    <option value="{{ $typeKey }}"
                                        {{ request('filter_type') == $typeKey ? 'selected' : '' }}>
                                        {{ $typeLabel }}
                                    </option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">

                            <x-ui.select name="filter_source" size="sm">

                                <option value="">—</option>

                                @foreach ($filterOptions['sources'] as $sourceId => $sourceName)

                                    <option value="{{ $sourceId }}"
                                        {{ request('filter_source') == $sourceId ? 'selected' : '' }}>
                                        {{ $sourceName }}
                                    </option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-3 py-2"></td>

                        <td class="px-3 py-2">

                            <x-ui.filter-input
                                type="text"
                                name="filter_name"
                                value="{{ request('filter_name') }}"
                                size="sm"
                            />

                        </td>

                        <td class="px-3 py-2">

                            <x-ui.filter-input
                                type="text"
                                name="filter_phone"
                                value="{{ request('filter_phone') }}"
                                size="sm"
                            />

                        </td>

                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2"></td>

                    </tr>

                </thead>

                <tbody>

                    @forelse ($orders as $index => $order)

                        <tr class="border-b border-border/60 transition-all hover:bg-primary/5 cursor-pointer
                            {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                            onclick="window.location.href='/orders/{{ $order->id }}'">

                            <td class="px-3 py-3 text-muted-foreground whitespace-nowrap">
                                {{ ($orders->firstItem() ?? 1) + $index }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap font-medium">
                                {{ $order->id }}
                            </td>

                            <td class="px-2 py-3 whitespace-nowrap">

                                <a href="/orders/{{ $order->id }}"
                                   target="_blank"
                                   class="text-muted-foreground hover:text-primary transition-colors"
                                   onclick="event.stopPropagation();">

                                    <svg class="w-4 h-4"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>

                                    </svg>

                                </a>

                            </td>

                            <td class="px-3 py-3 text-center whitespace-nowrap">

                                @if ($order->is_non_profile)
                                    <span class="text-primary font-bold">✓</span>
                                @endif

                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->city->name ?? '' }}
                            </td>

                            <td class="px-3 py-3 whitespace-nowrap">
                                <x-status-badge :status="$order->status" type="order" />
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $typeLabels[$order->type] ?? $order->type }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ implode(', ', array_filter([$order->settlement ?? '', $order->address ?? ''])) ?: '—' }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->sourceDisplayName() }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->order_time ? $order->order_time->format('d.m.Y, H:i') : '' }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->client_name }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->client_phone }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->created_local ? $order->created_local->format('d.m.Y, H:i') : '' }}
                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">

                                @if ($order->charge_amount > 0)

                                    {{ number_format($order->charge_amount, 0, ',', ' ') }} ₽

                                @endif

                            </td>

                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                {{ $order->creatorDisplayName() }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="15"
                                class="px-3 py-10 text-center text-muted-foreground">

                                Нет данных

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </form>

    <div class="px-4 py-3 border-t border-border bg-muted/10">

        <div class="text-sm text-muted-foreground">
            Найдено заявок: {{ $orders->total() }}
        </div>

    </div>

    @if ($orders->hasPages())

        <div class="px-4 py-3 border-t border-border">

            {{ $orders->links() }}

        </div>

    @endif

</x-ui.card>

@endsection