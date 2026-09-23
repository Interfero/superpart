@extends('layouts.app')

@section('title', 'Отчёт по городам — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Отчёт по городам', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">
        <div class="px-6 py-7">
            <h1 class="text-3xl font-bold text-foreground">Отчёт по городам</h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Принятые и закрытые заявки партнёрского сервиса в разрезе городов.
            </p>
        </div>
    </div>

    <div class="grid gap-4 mb-6 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.card>
            <div class="text-sm text-muted-foreground">Городов</div>
            <div class="mt-2 text-3xl font-bold text-foreground">{{ $cityStats->count() }}</div>
        </x-ui.card>

        <x-ui.card>
            <div class="text-sm text-muted-foreground">Принято</div>
            <div class="mt-2 text-3xl font-bold text-blue-500">{{ $totals['accepted_count'] }}</div>
        </x-ui.card>

        <x-ui.card>
            <div class="text-sm text-muted-foreground">Закрыто</div>
            <div class="mt-2 text-3xl font-bold text-green-500">{{ $totals['closed_count'] }}</div>
        </x-ui.card>

        <x-ui.card>
            <div class="text-sm text-muted-foreground">Начислено</div>
            <div class="mt-2 text-3xl font-bold text-green-500">
                {{ number_format($totals['total_charge'], 0, ',', ' ') }} ₽
            </div>
        </x-ui.card>
    </div>

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="px-4 pt-4">
            <x-date-filter
                action="/reports/cities"
                :dateFrom="request('date_from')"
                :dateTo="request('date_to')"
            />
        </div>

        <div class="overflow-x-auto">
            <table class="table-sticky w-full text-sm text-left">
                <thead class="sticky top-0 z-10 bg-card">
                    <tr class="border-b border-border bg-card/95 backdrop-blur">
                        <th class="px-4 py-3 text-muted-foreground font-normal">Город</th>
                        <th class="px-4 py-3 text-muted-foreground font-normal text-right">Принято</th>
                        <th class="px-4 py-3 text-muted-foreground font-normal text-right">Закрыто</th>
                        <th class="px-4 py-3 text-muted-foreground font-normal text-right">Начислено</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($cityStats as $index => $row)
                        <tr class="border-b border-border/60 transition-all hover:bg-primary/5 {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">
                            <td class="px-4 py-3 text-foreground font-medium">{{ $row['city_name'] }}</td>
                            <td class="px-4 py-3 text-foreground text-right">{{ $row['accepted_count'] }}</td>
                            <td class="px-4 py-3 text-foreground text-right">{{ $row['closed_count'] }}</td>
                            <td class="px-4 py-3 text-foreground text-right">
                                @if ($row['total_charge'] > 0)
                                    {{ number_format($row['total_charge'], 0, ',', ' ') }} ₽
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                Нет данных
                            </td>
                        </tr>
                    @endforelse

                    @if ($cityStats->isNotEmpty())
                        <tr class="border-t-2 border-border bg-muted/30">
                            <td class="px-4 py-3 text-foreground font-bold">Итого</td>
                            <td class="px-4 py-3 text-foreground font-bold text-right">{{ $totals['accepted_count'] }}</td>
                            <td class="px-4 py-3 text-foreground font-bold text-right">{{ $totals['closed_count'] }}</td>
                            <td class="px-4 py-3 text-foreground font-bold text-right">
                                {{ number_format($totals['total_charge'], 0, ',', ' ') }} ₽
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection
