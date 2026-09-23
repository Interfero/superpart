@extends('layouts.app')

@section('title', 'Отчёт по отзывам — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Отчёт по отзывам', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">
        <div class="flex flex-col gap-4 px-6 py-7 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-foreground">Отчёт по отзывам</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Сводка негативных отзывов по источнику, типу техники и городу.
                </p>
            </div>
            <x-ui.button tag="a" :href="route('reviews.create')" variant="primary">Добавить</x-ui.button>
        </div>
    </div>

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="px-4 pt-4">
            <x-date-filter
                action="{{ route('reports.reviews') }}"
                :dateFrom="request('date_from')"
                :dateTo="request('date_to')"
            />
        </div>

        <div class="overflow-x-auto">
            <table class="table-sticky w-full text-sm text-left">
                <thead class="sticky top-0 z-10 bg-card">
                    <tr class="border-b border-border bg-card/95 backdrop-blur">
                        <th class="px-4 py-3 font-normal text-muted-foreground">Источник</th>
                        <th class="px-4 py-3 font-normal text-muted-foreground">Тип техники</th>
                        <th class="px-4 py-3 font-normal text-muted-foreground">Город</th>
                        <th class="px-4 py-3 font-normal text-right text-muted-foreground">Кол-во заявок</th>
                        <th class="px-4 py-3 font-normal text-right text-muted-foreground">Решено</th>
                        <th class="px-4 py-3 font-normal text-right text-muted-foreground">Не решено</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($reviewStats as $index => $row)
                        <tr class="border-b border-border/60 transition-all hover:bg-primary/5 {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">
                            <td class="px-4 py-3 font-medium text-foreground">{{ $row['source_name'] }}</td>
                            <td class="px-4 py-3 text-foreground">{{ $row['equipment_name'] }}</td>
                            <td class="px-4 py-3 text-foreground">{{ $row['city_name'] }}</td>
                            <td class="px-4 py-3 text-right text-foreground">{{ $row['orders_count'] }}</td>
                            <td class="px-4 py-3 text-right font-medium text-green-500">{{ $row['resolved_count'] }}</td>
                            <td class="px-4 py-3 text-right font-medium text-red-500">{{ $row['not_resolved_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                Записей не найдено.
                            </td>
                        </tr>
                    @endforelse

                    @if ($reviewStats->isNotEmpty())
                        <tr class="border-t-2 border-border bg-muted/30 font-semibold">
                            <td class="px-4 py-3 text-foreground" colspan="3">Итого</td>
                            <td class="px-4 py-3 text-right text-foreground">{{ $totals['orders_count'] }}</td>
                            <td class="px-4 py-3 text-right text-green-500">{{ $totals['resolved_count'] }}</td>
                            <td class="px-4 py-3 text-right text-red-500">{{ $totals['not_resolved_count'] }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection
