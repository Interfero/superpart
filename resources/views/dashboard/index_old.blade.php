@extends('layouts.app')

@section('title', 'Главная — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Главная', 'url' => null]]" />
@endsection

@section('content')
    {{-- Блок быстрой статистики заработка --}}
    <x-ui.card padding="none" class="mt-4">
        <div class="grid grid-cols-1 divide-y divide-border lg:grid-cols-5 lg:divide-x lg:divide-y-0">
            @php
                $periods = [
                    ['label' => 'СЕГОДНЯ',         'key' => 'today'],
                    ['label' => 'ВЧЕРА',           'key' => 'yesterday'],
                    ['label' => 'ЭТА НЕДЕЛЯ',      'key' => 'this_week'],
                    ['label' => 'ЭТОТ МЕСЯЦ',      'key' => 'this_month'],
                    ['label' => 'ПРОШЛЫЙ МЕСЯЦ',   'key' => 'last_month'],
                ];
            @endphp

            @foreach ($periods as $period)
                <div class="min-w-0 px-4 py-4 text-center sm:px-6">
                    <div class="text-xs text-muted-foreground tracking-wider mb-2 break-words">{{ $period['label'] }}</div>
                    <div class="text-lg font-bold tabular-nums text-foreground break-words sm:text-xl">{{ number_format($earnings[$period['key']], 0, ',', ' ') }} Р</div>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    {{-- Таблица последних заказов --}}
    <x-ui.card padding="none" class="mt-6" data-table="dashboard-orders">
        <form method="GET" action="/" id="filter-form">
            <table class="table-sticky w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap w-10">▼</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="id">
                            <span class="inline-flex items-center gap-1">
                                ID заявки
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-2 py-3 text-muted-foreground font-normal whitespace-nowrap w-8"></th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">НПр</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="city">
                            <span class="inline-flex items-center gap-1">
                                Город
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="status">
                            <span class="inline-flex items-center gap-1">
                                Статус
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="type">
                            <span class="inline-flex items-center gap-1">
                                Вид
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="source">
                            <span class="inline-flex items-center gap-1">
                                Источник
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="order_time">
                            <span class="inline-flex items-center gap-1">
                                Время заявки
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="client_name">
                            <span class="inline-flex items-center gap-1">
                                Имя
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="client_phone">
                            <span class="inline-flex items-center gap-1">
                                Телефон
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="created_local">
                            <span class="inline-flex items-center gap-1">
                                Создано (лок)
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="charge_amount">
                            <span class="inline-flex items-center gap-1">
                                Начисление
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap cursor-pointer select-none hover:text-foreground transition-colors"
                            data-sort-key="employee">
                            <span class="inline-flex items-center gap-1">
                                Сотрудник
                                <span class="sort-icon text-muted-foreground" data-sort-icon>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                    </tr>

                    {{-- Строка фильтров --}}
                    <tr class="border-b border-border bg-muted/10">
                        <td class="px-3 py-2"></td>
                        {{-- ID заявки --}}
                        <td class="px-3 py-2">
                            <x-ui.filter-input
                                type="text"
                                name="filter_id"
                                value="{{ request('filter_id') }}"
                                size="sm"
                                class="table-filter"
                                data-filter-key="id"
                                placeholder=""
                            />
                        </td>
                        <td class="px-2 py-2"></td>
                        <td class="px-3 py-2"></td>
                        {{-- Город --}}
                        <td class="px-3 py-2">
                            <x-ui.select name="filter_city" size="sm" class="table-filter" data-filter-key="city">
                                <option value="">—</option>
                                @foreach ($filterOptions['cities'] as $cityId => $cityName)
                                    <option value="{{ $cityId }}" {{ request('filter_city') == $cityId ? 'selected' : '' }}>{{ $cityName }}</option>
                                @endforeach
                            </x-ui.select>
                        </td>
                        {{-- Статус --}}
                        <td class="px-3 py-2">
                            <x-ui.select name="filter_status" size="sm" class="table-filter" data-filter-key="status">
                                <option value="">—</option>
                                @foreach ($filterOptions['statuses'] as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" {{ request('filter_status') == $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                @endforeach
                            </x-ui.select>
                        </td>
                        {{-- Вид --}}
                        <td class="px-3 py-2">
                            <x-ui.select name="filter_type" size="sm" class="table-filter" data-filter-key="type">
                                <option value="">—</option>
                                @foreach ($filterOptions['types'] as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" {{ request('filter_type') == $typeKey ? 'selected' : '' }}>{{ $typeLabel }}</option>
                                @endforeach
                            </x-ui.select>
                        </td>
                        {{-- Источник --}}
                        <td class="px-3 py-2">
                            <x-ui.select name="filter_source" size="sm" class="table-filter" data-filter-key="source">
                                <option value="">—</option>
                                @foreach ($filterOptions['sources'] as $sourceId => $sourceName)
                                    <option value="{{ $sourceId }}" {{ request('filter_source') == $sourceId ? 'selected' : '' }}>{{ $sourceName }}</option>
                                @endforeach
                            </x-ui.select>
                        </td>
                        <td class="px-3 py-2"></td>
                        {{-- Имя --}}
                        <td class="px-3 py-2">
                            <x-ui.filter-input
                                type="text"
                                name="filter_name"
                                value="{{ request('filter_name') }}"
                                size="sm"
                                class="table-filter"
                                data-filter-key="client_name"
                                placeholder=""
                            />
                        </td>
                        {{-- Телефон --}}
                        <td class="px-3 py-2">
                            <x-ui.filter-input
                                type="text"
                                name="filter_phone"
                                value="{{ request('filter_phone') }}"
                                size="sm"
                                class="table-filter"
                                data-filter-key="client_phone"
                                placeholder=""
                            />
                        </td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2"></td>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($orders as $index => $order)
                        @php
                            $typeLabels = ['first_time' => 'Впервые', 'first' => 'Впервые', 'warranty' => 'Гарантия', 'repeat' => 'Повтор'];
                        @endphp
                        <tr class="border-b border-border/60 transition-colors hover:bg-muted/40 cursor-pointer
                                   {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                            data-row
                            data-href="/orders/{{ $order->id }}"
                            data-col-id="{{ $order->id }}"
                            data-col-city="{{ $order->city->name ?? '' }}"
                            data-col-status="{{ $order->status }}"
                            data-col-type="{{ $order->type }}"
                            data-col-source="{{ $order->sourceDisplayName() }}"
                            data-col-order_time="{{ $order->order_time ? $order->order_time->format('d.m.Y, H:i') : '' }}"
                            data-col-client_name="{{ $order->client_name }}"
                            data-col-client_phone="{{ $order->client_phone }}"
                            data-col-created_local="{{ $order->created_local ? $order->created_local->format('d.m.Y, H:i') : '' }}"
                            data-col-charge_amount="{{ $order->charge_amount }}"
                            data-col-employee="{{ $order->employee->name ?? '' }}"
                        >
                            <td class="px-3 py-3 text-muted-foreground whitespace-nowrap">{{ $index + 1 }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap font-medium">{{ $order->id }}</td>
                            <td class="px-2 py-3 whitespace-nowrap">
                                <a href="/orders/{{ $order->id }}" target="_blank"
                                   class="text-muted-foreground hover:text-primary transition-colors"
                                   onclick="event.stopPropagation();"
                                   title="Открыть в новой вкладке">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                @if ($order->is_non_profile)
                                    <span class="text-primary font-bold">✓</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->city->name ?? '' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <x-status-badge :status="$order->status" type="order" />
                            </td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $typeLabels[$order->type] ?? $order->type }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->sourceDisplayName() }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->order_time ? $order->order_time->format('d.m.Y, H:i') : '' }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->client_name }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->client_phone }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->created_local ? $order->created_local->format('d.m.Y, H:i') : '' }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                @if ($order->charge_amount > 0)
                                    {{ number_format($order->charge_amount, 0, ',', ' ') }} Р
                                @endif
                            </td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->employee->name ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-3 py-8 text-center text-muted-foreground">
                                Нет данных
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </form>
    </x-ui.card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('[data-table="dashboard-orders"]');
    if (!container) return;

    container.querySelectorAll('[data-row]').forEach(row => {
        row.addEventListener('click', () => {
            const href = row.dataset.href;
            if (href) window.location.href = href;
        });
    });

    const form = document.getElementById('filter-form');
    if (form) window.superpartBindFilterForm(form);
});
</script>
@endpush
