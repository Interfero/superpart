@extends('layouts.app')

@section('title', 'Отчёт по заявкам — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Отчёт по заявкам', 'url' => null],
    ]" />
@endsection

@section('content')

    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

        <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">

            <div>

                <h1 class="text-3xl font-bold text-foreground">
                    Отчёт по заявкам
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    @if (auth()->user()->isManager())
                        Аналитика заявок, статусов и работы сотрудников.
                    @else
                        Аналитика заявок, начислений, статусов и работы сотрудников.
                    @endif
                </p>

            </div>

        </div>

    </div>

    @php

        $totalOrders = $orders->total();

        $completedOrders = collect($orders->items())
            ->whereNotNull('closed_local')
            ->count();

        $inWorkOrders = collect($orders->items())
            ->whereIn('status', ['in_work', 'in_work_sd'])
            ->count();

        $hideCharges = auth()->user()->isManager();
        $totalCharges = $hideCharges
            ? 0
            : collect($orders->items())->sum('charge_amount');

    @endphp

    <div @class([
        'grid gap-4 mb-6 md:grid-cols-2',
        'xl:grid-cols-3' => $hideCharges,
        'xl:grid-cols-4' => ! $hideCharges,
    ])>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Всего заявок
            </div>

            <div class="mt-2 text-3xl font-bold text-foreground">
                {{ $totalOrders }}
            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                Выполнено
            </div>

            <div class="mt-2 text-3xl font-bold text-green-500">
                {{ $completedOrders }}
            </div>

        </x-ui.card>

        <x-ui.card>

            <div class="text-sm text-muted-foreground">
                В работе
            </div>

            <div class="mt-2 text-3xl font-bold text-blue-500">
                {{ $inWorkOrders }}
            </div>

        </x-ui.card>

        @unless ($hideCharges)
            <x-ui.card>

                <div class="text-sm text-muted-foreground">
                    Начисления
                </div>

                <div class="mt-2 text-3xl font-bold text-primary">
                    {{ number_format($totalCharges, 0, ',', ' ') }} ₽
                </div>

            </x-ui.card>
        @endunless

    </div>

    <x-ui.card padding="none" class="overflow-hidden">

        <div class="flex flex-wrap items-center gap-2 px-4 pt-4">
            <div class="min-w-0 flex-1">
                <x-tabs :tabs="$tabs" :active="$activeTab" url="/reports/orders" />
            </div>
            <div class="dropdown-menu relative" data-dropdown>
                <button
                    type="button"
                    class="rounded border border-border p-2 text-muted-foreground transition-colors hover:text-foreground"
                    title="Экспорт по текущим фильтрам"
                    data-dropdown-toggle
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                </button>
                <div
                    class="dropdown-panel invisible absolute right-0 top-full z-50 mt-1 min-w-[160px] rounded-md border border-border bg-card opacity-0 shadow-xl transition-all duration-200"
                    data-dropdown-panel
                >
                    <a
                        href="{{ route('reports.orders.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
                        class="block rounded-t-md px-4 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        Excel (XLSX)
                    </a>
                    <a
                        href="{{ route('reports.orders.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                        class="block rounded-b-md px-4 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        CSV
                    </a>
                </div>
            </div>
        </div>

        <div class="px-4 pt-4">
            <x-date-filter
                action="/reports/orders"
                :showClosedDates="true"
                :showWorkType="true"
                :dateFrom="request('date_from')"
                :dateTo="request('date_to')"
                :closedFrom="request('closed_from')"
                :closedTo="request('closed_to')"
                :workTypes="$workTypes"
                :selectedWorkType="request('work_type')"
            >
                <input type="hidden" name="status" value="{{ $activeTab }}">
                <div>
                    <label class="block text-xs text-muted-foreground mb-1">Источник</label>
                    <x-ui.select name="filter_source" class="min-w-[220px]">
                        <option value="">Все источники</option>
                        @foreach ($filterOptions['sources'] as $sourceId => $sourceName)
                            <option value="{{ $sourceId }}" @selected(request('filter_source') == $sourceId)>
                                {{ $sourceName }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
            </x-date-filter>
        </div>

        <form method="GET" action="/reports/orders" id="report-orders-filter-form">

            <input type="hidden" name="status" value="{{ $activeTab }}">

            @if (request('date_from'))
                <input type="hidden" name="date_from" value="{{ request('date_from') }}">
            @endif

            @if (request('date_to'))
                <input type="hidden" name="date_to" value="{{ request('date_to') }}">
            @endif

            @if (request('closed_from'))
                <input type="hidden" name="closed_from" value="{{ request('closed_from') }}">
            @endif

            @if (request('closed_to'))
                <input type="hidden" name="closed_to" value="{{ request('closed_to') }}">
            @endif

            @if (request('work_type'))
                <input type="hidden" name="work_type" value="{{ request('work_type') }}">
            @endif

            @if (request('sort_by'))
                <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
            @endif

            @if (request('sort_dir'))
                <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
            @endif

            @if (request('filter_id'))
                <p class="mb-3 text-xs text-muted-foreground">
                    Поиск по ID: фильтр дат не применяется (чтобы находить заявки за любой период).
                </p>
            @endif

            <div class="overflow-x-auto">

                <table class="table-sticky w-full text-sm text-left">

                    <thead class="sticky top-0 z-10 bg-card">

                        <tr class="border-b border-border bg-card/95 backdrop-blur">

                            <x-sortable-th key="id" label="ID заявки" />

                            <x-sortable-th key="reference_source_id" label="Источник" />

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap" title="Профильный заказ">
                                НПр
                            </th>

                            <x-sortable-th key="city_id" label="Город" />

                            <x-sortable-th key="status" label="Статус" />

                            <x-sortable-th key="type" label="Тип" />

                            <th class="table-col-address px-3 py-3 text-muted-foreground font-normal">
                                Нас. пункт, адрес
                            </th>

                            <x-sortable-th key="work_type_id" label="Вид работ" />

                            <x-sortable-th key="order_time" label="Время заявки" />

                            <x-sortable-th key="client_name" label="Имя" />

                            <x-sortable-th key="client_phone" label="Телефон" />

                            <x-sortable-th key="created_local" label="Создано" />

                            <x-sortable-th key="closed_local" label="Закрыто" />

                            @unless ($hideCharges)
                                <x-sortable-th key="charge_amount" label="Начисление" align="right" />
                            @endunless

                            <x-sortable-th key="user_id" label="Сотрудник" />

                        </tr>

                        <tr class="border-b border-border bg-muted/30">

                            <td class="px-3 py-2">
                                <x-ui.filter-input
                                    type="text"
                                    name="filter_id"
                                    value="{{ request('filter_id') }}"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="id"
                                />
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_source"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="source"
                                >
                                    <option value="">—</option>

                                    @foreach ($filterOptions['sources'] as $sourceId => $sourceName)

                                        <option
                                            value="{{ $sourceId }}"
                                            {{ request('filter_source') == $sourceId ? 'selected' : '' }}
                                        >
                                            {{ $sourceName }}
                                        </option>

                                    @endforeach

                                </x-ui.select>
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_profile"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="profile"
                                    title="Профильный заказ"
                                >
                                    <option value="">—</option>
                                    <option value="1" {{ request('filter_profile') === '1' ? 'selected' : '' }}>
                                        Да
                                    </option>
                                    <option value="0" {{ request('filter_profile') === '0' ? 'selected' : '' }}>
                                        Нет
                                    </option>
                                </x-ui.select>
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_city"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="city"
                                >
                                    <option value="">—</option>

                                    @foreach ($filterOptions['cities'] as $cityId => $cityName)

                                        <option
                                            value="{{ $cityId }}"
                                            {{ request('filter_city') == $cityId ? 'selected' : '' }}
                                        >
                                            {{ $cityName }}
                                        </option>

                                    @endforeach

                                </x-ui.select>
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_status"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="status"
                                >
                                    <option value="">—</option>

                                    @foreach ($filterOptions['statuses'] as $statusKey => $statusLabel)

                                        <option
                                            value="{{ $statusKey }}"
                                            {{ request('filter_status') == $statusKey ? 'selected' : '' }}
                                        >
                                            {{ $statusLabel }}
                                        </option>

                                    @endforeach

                                </x-ui.select>
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_type"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="type"
                                >
                                    <option value="">—</option>

                                    @foreach ($filterOptions['types'] as $typeKey => $typeLabel)

                                        <option
                                            value="{{ $typeKey }}"
                                            {{ request('filter_type') == $typeKey ? 'selected' : '' }}
                                        >
                                            {{ $typeLabel }}
                                        </option>

                                    @endforeach

                                </x-ui.select>
                            </td>

                            <td class="px-3 py-2"></td>

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_work_type"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="work_type"
                                >
                                    <option value="">—</option>

                                    @foreach ($filterOptions['workTypes'] as $wtId => $wtName)

                                        <option
                                            value="{{ $wtId }}"
                                            {{ request('filter_work_type') == $wtId ? 'selected' : '' }}
                                        >
                                            {{ $wtName }}
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
                                    class="table-filter"
                                    data-filter-key="client_name"
                                />
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.filter-input
                                    type="text"
                                    name="filter_phone"
                                    value="{{ request('filter_phone') }}"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="client_phone"
                                />
                            </td>

                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2"></td>
                            @unless ($hideCharges)
                                <td class="px-3 py-2"></td>
                            @endunless

                            <td class="px-3 py-2">
                                <x-ui.select
                                    name="filter_employee"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="employee"
                                >
                                    <option value="">—</option>

                                    @foreach ($filterOptions['employees'] as $empId => $empName)

                                        <option
                                            value="{{ $empId }}"
                                            {{ request('filter_employee') == $empId ? 'selected' : '' }}
                                        >
                                            {{ $empName }}
                                        </option>

                                    @endforeach

                                </x-ui.select>
                            </td>

                        </tr>

                    </thead>

                    <tbody>

                        @php
                            $typeLabels = [
                                'first_time' => 'Впервые',
                                'warranty' => 'Гарантия',
                                'repeat' => 'Повтор',
                            ];
                        @endphp

                        @forelse ($orders as $index => $order)

                            <tr
                                class="border-b border-border/60 transition-all hover:bg-primary/5 cursor-pointer
                                {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                                data-row
                                data-href="/orders/{{ $order->id }}"
                            >

                                <td class="px-3 py-3 text-foreground whitespace-nowrap font-medium">
                                    {{ $order->id }}
                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                    {{ $order->sourceDisplayName() }}
                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap" title="{{ $order->is_non_profile ? 'Непрофиль' : 'Профиль' }}">
                                    {{ $order->is_non_profile ? 'Нет' : 'Да' }}
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

                                @php
                                    $addressLine = implode(', ', array_filter([$order->settlement ?? '', $order->address ?? ''])) ?: '—';
                                @endphp
                                <td class="table-col-address px-3 py-3 text-foreground" @if ($addressLine !== '—') title="{{ $addressLine }}" @endif>
                                    <span class="table-col-address__text">{{ $addressLine }}</span>
                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                    {{ $order->workType->name ?? '' }}
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
                                    {{ $order->closed_local ? $order->closed_local->format('d.m.Y, H:i') : '' }}
                                </td>

                                @unless ($hideCharges)
                                    <td class="px-3 py-3 text-foreground whitespace-nowrap text-right">

                                        @if ($order->charge_amount > 0)

                                            {{ number_format($order->charge_amount, 0, ',', ' ') }} ₽

                                        @endif

                                    </td>
                                @endunless

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                    {{ $order->creatorDisplayName() }}
                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="{{ $hideCharges ? 14 : 15 }}" class="px-3 py-10 text-center text-muted-foreground">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('report-orders-filter-form');

    if (!form) return;

    form.querySelectorAll('[data-row]').forEach(row => {

        row.addEventListener('click', () => {

            const href = row.dataset.href;

            if (href) {
                window.location.href = href;
            }

        });

    });

    if (typeof window.superpartBindFilterForm === 'function') {
        window.superpartBindFilterForm(form);
    }

});
</script>
@endpush
