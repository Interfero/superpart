@extends('layouts.app')

@section('title', 'Работа с отзывами — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Работа с отзывами', 'url' => null],
    ]" />
@endsection

@section('content')

    <div class="space-y-6">

        <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">

            <div class="px-6 py-7">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                    <h1 class="text-3xl font-bold text-foreground">
                        Работа с отзывами
                    </h1>

                    <x-ui.button
                        tag="a"
                        :href="route('reviews.create')"
                        variant="primary"
                        size="md"
                        class="shrink-0"
                    >
                        Добавить отзыв
                    </x-ui.button>
                </div>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Управление негативными отзывами, контроль возвратов,
                    результатов и работы по клиентским обращениям.
                </p>
            </div>

        </div>

        @if (session('success'))

            <x-ui.alert type="success">
                {{ session('success') }}
            </x-ui.alert>

        @endif

        @php

            $totalReviews = $reviews->total();

            $newReviews = collect($reviews->items())
                ->where('status', 'new')
                ->count();

            $resolvedReviews = collect($reviews->items())
                ->where('status', 'resolved')
                ->count();

            $notResolvedReviews = collect($reviews->items())
                ->where('status', 'not_resolved')
                ->count();

        @endphp

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

            <x-ui.card>

                <div class="text-sm text-muted-foreground">
                    Всего отзывов
                </div>

                <div class="mt-2 text-3xl font-bold text-foreground">
                    {{ $totalReviews }}
                </div>

            </x-ui.card>

            <x-ui.card>

                <div class="text-sm text-muted-foreground">
                    Новые
                </div>

                <div class="mt-2 text-3xl font-bold text-yellow-500">
                    {{ $newReviews }}
                </div>

            </x-ui.card>

            <x-ui.card>

                <div class="text-sm text-muted-foreground">
                    Решённые
                </div>

                <div class="mt-2 text-3xl font-bold text-green-500">
                    {{ $resolvedReviews }}
                </div>

            </x-ui.card>

            <x-ui.card>

                <div class="text-sm text-muted-foreground">
                    Не решены
                </div>

                <div class="mt-2 text-3xl font-bold text-red-500">
                    {{ $notResolvedReviews }}
                </div>

            </x-ui.card>

        </div>

        <x-ui.card class="overflow-hidden" padding="none">

            <div class="px-4 pt-4">

                <x-date-filter
                    :action="route('reviews.index')"
                    :dateFrom="$dateFrom"
                    :dateTo="$dateTo"
                />

            </div>

            <div class="overflow-x-auto">

                <table class="table-sticky w-full text-sm text-left">

                    <thead class="sticky top-0 z-10 bg-card">

                        <tr class="border-b border-border bg-card/95 backdrop-blur">

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                ID
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Заказ
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Статус
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Результат
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Отзыв
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Создан
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Закрыт
                            </th>

                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">
                                Город
                            </th>

                        </tr>

                        <tr class="border-b border-border bg-muted/30">

                            <td class="px-3 py-2">
                                <x-ui.filter-input
                                    type="text"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="id"
                                />
                            </td>

                            <td class="px-3 py-2">
                                <x-ui.filter-input
                                    type="text"
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="order_id"
                                />
                            </td>

                            <td class="px-3 py-2">

                                <x-ui.select
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="status"
                                >

                                    <option value="">—</option>

                                    @foreach ($statusLabels as $value => $label)

                                        <option value="{{ $label }}">
                                            {{ $label }}
                                        </option>

                                    @endforeach

                                </x-ui.select>

                            </td>

                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2"></td>

                            <td class="px-3 py-2">

                                <x-ui.select
                                    size="sm"
                                    class="table-filter"
                                    data-filter-key="city"
                                >

                                    <option value="">—</option>

                                    @foreach ($cities as $cityName)

                                        <option value="{{ $cityName }}">
                                            {{ $cityName }}
                                        </option>

                                    @endforeach

                                </x-ui.select>

                            </td>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($reviews as $index => $review)

                            <tr
                                class="border-b border-border/60 transition-all hover:bg-primary/5 cursor-pointer
                                {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                                data-row
                                data-href="{{ route('reviews.show', $review) }}"
                                data-col-id="{{ $review->id }}"
                                data-col-order_id="{{ $review->order_id }}"
                                data-col-status="{{ $statusLabels[$review->status] ?? $review->status }}"
                                data-col-city="{{ $review->city->name ?? '' }}"
                            >

                                <td class="px-3 py-3 text-foreground font-medium whitespace-nowrap">
                                    {{ $review->id }}
                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">

                                    @if ($review->order_id)

                                        <a
                                            href="{{ route('orders.show', $review->order_id) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-primary hover:underline"
                                            onclick="event.stopPropagation();"
                                        >
                                            #{{ $review->order_id }}
                                        </a>

                                    @else

                                        —

                                    @endif

                                </td>

                                <td class="px-3 py-3 whitespace-nowrap">

                                    <x-status-badge
                                        :status="$review->status"
                                        type="review"
                                    />

                                </td>

                                <td class="px-3 py-3 whitespace-nowrap">

                                    @if ($review->result === 'resolved')

                                        <span class="text-green-500 font-medium">
                                            Решено
                                        </span>

                                    @elseif ($review->result === 'not_resolved')

                                        <span class="text-red-500 font-medium">
                                            Не решено
                                        </span>

                                    @elseif ($review->result === 'refund')

                                        <span class="text-yellow-500 font-medium">
                                            Возврат
                                        </span>

                                    @else

                                        —

                                    @endif

                                </td>

                                <td class="px-3 py-3 text-foreground max-w-[340px]">

                                    @if ($review->review_url)

                                        <a
                                            href="{{ $review->review_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            onclick="event.stopPropagation();"
                                            class="text-primary hover:text-primary/80 underline break-all text-xs"
                                        >
                                            {{ Str::limit($review->review_url, 70) }}
                                        </a>

                                    @endif

                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                    {{ $review->created_at?->format('d.m.Y H:i') }}
                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                    {{ $review->closed_at?->format('d.m.Y H:i') ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                    {{ $review->city->name ?? '' }}
                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="8" class="px-3 py-10 text-center text-muted-foreground">
                                    Нет отзывов
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

            <div class="px-4 py-3 border-t border-border bg-muted/10">

                <div class="text-sm text-muted-foreground">
                    Найдено отзывов: {{ $reviews->total() }}
                </div>

            </div>

        </x-ui.card>

        @if ($reviews->hasPages())

            <div>
                {{ $reviews->links() }}
            </div>

        @endif

    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('tr[data-href]').forEach((row) => {

        row.addEventListener('click', (e) => {

            if (e.target.closest('a, button, input, select')) {
                return;
            }

            window.location.href = row.dataset.href;

        });

    });

    document.querySelectorAll('.table-filter').forEach(function (filter) {

        const isSelect = filter.tagName === 'SELECT';

        function applyFilters() {

            const rows = document.querySelectorAll('tr[data-row]');
            const allFilters = document.querySelectorAll('.table-filter');

            rows.forEach(function (row) {

                let visible = true;

                allFilters.forEach(function (f) {

                    const fKey = f.dataset.filterKey;
                    const fVal = f.value.toLowerCase().trim();

                    if (!fVal) return;

                    const cellVal =
                        (
                            row.dataset[
                                'col' +
                                fKey.charAt(0).toUpperCase() +
                                fKey.slice(1)
                            ] ||
                            row.getAttribute('data-col-' + fKey) ||
                            ''
                        ).toLowerCase();

                    if (f.tagName === 'SELECT') {

                        if (cellVal !== fVal) {
                            visible = false;
                        }

                    } else {

                        if (!cellVal.includes(fVal)) {
                            visible = false;
                        }

                    }

                });

                row.style.display = visible ? '' : 'none';

            });

        }

        if (isSelect) {

            filter.addEventListener('change', applyFilters);

        } else {

            filter.addEventListener('input', applyFilters);

        }

    });

});
</script>
@endpush