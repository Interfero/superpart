@extends('layouts.app')

@section('title', 'Список начислений — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Список начислений', 'url' => null],
    ]" />
@endsection

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">

        <div>

            <h1 class="text-3xl font-bold text-foreground">
                Список начислений
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Финансовые операции, начисления, списания и контроль баланса партнёров.
            </p>

        </div>

    </div>

</div>

@php

    $totalAmount = collect($transactions->items())->sum('amount');

    $incomeAmount = collect($transactions->items())
        ->where('operation_type', 'charge')
        ->sum('amount');

    $expenseAmount = collect($transactions->items())
        ->whereIn('operation_type', ['withdrawal', 'correction'])
        ->sum('amount');

@endphp

<div class="grid gap-4 mb-6 md:grid-cols-2 xl:grid-cols-4">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Всего операций
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ $transactions->total() }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Начислено
        </div>

        <div class="mt-2 text-3xl font-bold text-green-500">
            {{ number_format($incomeAmount, 0, ',', ' ') }} ₽
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Списано
        </div>

        <div class="mt-2 text-3xl font-bold text-red-500">
            {{ number_format($expenseAmount, 0, ',', ' ') }} ₽
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Текущий баланс
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ number_format($currentBalance ?? 0, 0, ',', ' ') }} ₽
        </div>

    </x-ui.card>

</div>

<x-ui.card padding="lg" class="shadow-sm">

    <x-date-filter
        action="{{ route('transactions.index') }}"
        :date-from="$dateFrom"
        :date-to="$dateTo"
    >
        @if (! empty($filterId))
            <input type="hidden" name="filter_id" value="{{ $filterId }}">
        @endif
        @if (! empty($filterSource))
            <input type="hidden" name="filter_source" value="{{ $filterSource }}">
        @endif
    </x-date-filter>

    <form
        method="GET"
        action="{{ route('transactions.index') }}"
        id="transactions-filter-form"
        class="mt-6">

        @if ($dateFrom)
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        @endif

        @if ($dateTo)
            <input type="hidden" name="date_to" value="{{ $dateTo }}">
        @endif

        <div class="overflow-x-auto rounded-2xl border border-border">

            <table class="table-sticky w-full text-sm text-left">

                <thead class="sticky top-0 z-10 bg-card">

                    <tr class="border-b border-border bg-card/95 backdrop-blur">

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Начисление
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Выполнено
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Заявка
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap text-right">
                            Начислено
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Город
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Источник
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Операция
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap text-right">
                            Остаток
                        </th>

                    </tr>

                    <tr class="border-b border-border bg-muted/20">

                        <td class="px-4 py-2 w-[7rem] max-w-[7rem]">

                            <x-ui.filter-input
                                type="text"
                                name="filter_id"
                                value="{{ $filterId }}"
                                size="sm"
                                narrow
                                inputmode="numeric"
                                class="table-filter"
                                data-filter-key="id"
                            />

                        </td>

                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2"></td>

                        <td class="px-4 py-2 min-w-[10rem]">
                            <x-ui.select
                                name="filter_source"
                                size="sm"
                                class="table-filter table-filter-select"
                                data-filter-key="source"
                            >
                                <option value="">—</option>
                                @foreach (($sourceOptions ?? []) as $sourceKey => $sourceName)
                                    <option
                                        value="{{ $sourceKey }}"
                                        @selected(($filterSource ?? '') === (string) $sourceKey)
                                    >
                                        {{ $sourceName }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </td>

                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2"></td>

                    </tr>

                </thead>

                <tbody>

                    @forelse ($transactions as $index => $transaction)

                        <tr
                            class="border-b border-border/60 transition-all hover:bg-primary/5
                            {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}
                            {{ $transaction->order_id ? 'cursor-pointer' : '' }}"
                            data-row
                            data-col-id="{{ $transaction->id }}"
                            @if ($transaction->order_id)
                                data-href="{{ route('orders.show', $transaction->order_id) }}"
                            @endif
                        >

                            <td class="px-4 py-3 text-foreground whitespace-nowrap font-medium">
                                {{ $transaction->id }}
                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap">
                                {{ $transaction->completed_at->format('d.m.Y, H:i') }}
                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap">

                                @if ($transaction->order_id)

                                    <a
                                        href="{{ route('orders.show', $transaction->order_id) }}"
                                        class="text-primary hover:underline">

                                        {{ $transaction->order_id }}

                                    </a>

                                @else

                                    —

                                @endif

                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-right">

                                <span class="{{ $transaction->amount >= 0 ? 'text-green-500' : 'text-red-500' }} font-semibold">

                                    {{ number_format($transaction->amount, 0, ',', ' ') }} ₽

                                </span>

                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap">
                                {{ $transaction->order?->city?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap">
                                {{ $transaction->order?->sourceDisplayName() ?? '—' }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">

                                <x-status-badge
                                    :status="$transaction->operation_type"
                                    type="transaction"
                                />

                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap text-right font-semibold">

                                {{ number_format($transaction->balance_after, 0, ',', ' ') }} ₽

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="8"
                                class="px-4 py-10 text-center text-muted-foreground">

                                Нет данных

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </form>

    <div class="flex flex-wrap items-center justify-between gap-3 px-1 pt-6">

        <div class="text-sm text-muted-foreground">

            Найдено операций:
            <span class="font-semibold text-foreground">
                {{ $transactions->total() }}
            </span>

        </div>

        @if ($transactions->hasPages())

            <div>

                {{ $transactions->links() }}

            </div>

        @endif

    </div>

</x-ui.card>

@endsection

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', () => {

    const form =
        document.getElementById('transactions-filter-form');

    if (!form) return;

    if (typeof window.superpartBindFilterForm === 'function') {

        window.superpartBindFilterForm(form);

    }

    document
        .querySelectorAll('#transactions-filter-form tr[data-href]')
        .forEach((row) => {

            row.addEventListener('click', (e) => {

                if (
                    e.target.closest(
                        'input, select, button, a, label'
                    )
                ) {
                    return;
                }

                window.location.href = row.dataset.href;

            });

        });

});
</script>

@endpush