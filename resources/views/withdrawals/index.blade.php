@extends('layouts.app')

@section('title', 'Заявки на вывод д/с — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Заявки на вывод д/с', 'url' => null],
    ]" />
@endsection

@section('content')

@if (session('success'))
    <x-ui.alert type="success" class="mb-4">
        {{ session('success') }}
    </x-ui.alert>
@endif

@if ($errors->any())
    <x-ui.alert type="error" class="mb-4">
        <ul class="list-disc list-inside text-sm">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">

        <div>

            <h1 class="text-3xl font-bold text-foreground">
                Заявки на вывод денежных средств
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Управление выплатами, статусами заявок и финансовыми операциями.
            </p>

        </div>

    </div>

</div>

<div class="grid gap-4 mb-6 md:grid-cols-3">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Всего заявок
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground">
            {{ $countAll }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            В работе
        </div>

        <div class="mt-2 text-3xl font-bold text-amber-500">
            {{ $countInWork }}
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Выполнено
        </div>

        <div class="mt-2 text-3xl font-bold text-green-500">
            {{ $countCompleted }}
        </div>

    </x-ui.card>

</div>

<x-ui.card padding="lg" class="shadow-sm">

    <x-date-filter
        action="{{ route('withdrawals.index') }}"
        :date-from="$dateFrom"
        :date-to="$dateTo"
    />

    <div class="mt-6">

        <x-tabs
            :tabs="[
                ['key' => 'all', 'label' => 'Все заявки', 'count' => $countAll],
                ['key' => 'in_work', 'label' => 'В работе', 'count' => $countInWork],
                ['key' => 'completed', 'label' => 'Выполнен', 'count' => $countCompleted],
            ]"
            :active="$activeStatus"
            :url="route('withdrawals.index')"
        />

    </div>

    <form
        method="GET"
        action="{{ route('withdrawals.index') }}"
        id="withdrawals-filter-form"
        class="mt-6">

        @if ($dateFrom)
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        @endif

        @if ($dateTo)
            <input type="hidden" name="date_to" value="{{ $dateTo }}">
        @endif

        @if ($activeStatus !== 'all')
            <input type="hidden" name="status" value="{{ $activeStatus }}">
        @endif

        <div class="overflow-x-auto rounded-2xl border border-border">

            <table class="table-sticky w-full text-sm text-left">

                <thead class="sticky top-0 z-10 bg-card">

                    <tr class="border-b border-border bg-card/95 backdrop-blur">

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            ID
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Создание
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap">
                            Статус
                        </th>

                        <th class="px-4 py-3 text-muted-foreground font-normal whitespace-nowrap text-right">
                            Сумма
                        </th>

                    </tr>

                    <tr class="border-b border-border bg-muted/20">

                        <td class="px-4 py-2 w-[7rem] max-w-[7rem]">

                            <x-ui.filter-input
                                type="text"
                                name="search"
                                value="{{ $search }}"
                                size="sm"
                                narrow
                                inputmode="numeric"
                                class="table-filter"
                                data-filter-key="id"
                            />

                        </td>

                        <td class="px-4 py-2"></td>

                        <td class="px-4 py-2">

                            <x-ui.select
                                name="filter_status"
                                size="sm"
                                class="table-filter table-filter-select"
                                data-filter-key="status">

                                <option value="">—</option>

                                @foreach ($statusLabels as $value => $label)

                                    <option value="{{ $value }}"
                                        @selected($filterStatus === $value)>

                                        {{ $label }}

                                    </option>

                                @endforeach

                            </x-ui.select>

                        </td>

                        <td class="px-4 py-2"></td>

                    </tr>

                </thead>

                <tbody>

                    @forelse ($withdrawals as $index => $withdrawal)

                        <tr
                            class="border-b border-border/60 transition-all hover:bg-primary/5 cursor-pointer
                            {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                            data-row
                            data-href="{{ route('withdrawals.show', $withdrawal->id) }}"
                            data-col-id="{{ $withdrawal->id }}"
                            data-col-status="{{ $withdrawal->status }}"
                        >

                            <td class="px-4 py-3 text-foreground whitespace-nowrap font-medium">
                                {{ $withdrawal->index_id_label ?? $withdrawal->id }}
                                @if (($withdrawal->index_batch_size ?? 1) > 1)
                                    <div class="text-xs font-normal text-muted-foreground">{{ $withdrawal->index_batch_size }} частей</div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap">
                                {{ $withdrawal->created_at->format('d.m.Y, H:i') }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">

                                <x-status-badge
                                    :status="$withdrawal->status"
                                    type="withdrawal"
                                />

                            </td>

                            <td class="px-4 py-3 text-foreground whitespace-nowrap text-right font-semibold">

                                {{ number_format($withdrawal->total_amount, 0, ',', ' ') }}

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="4"
                                class="px-4 py-10 text-center text-muted-foreground">

                                Нет данных

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </form>

    @if ($withdrawals->hasPages())

        <div class="pt-6 border-t border-border mt-6">

            {{ $withdrawals->links() }}

        </div>

    @endif

</x-ui.card>

@endsection

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', () => {

    const form =
        document.getElementById('withdrawals-filter-form');

    if (!form) return;

    window.superpartBindFilterForm(form);

    document.querySelectorAll('tr[data-href]').forEach(row => {

        row.addEventListener('click', (e) => {

            if (e.target.closest('input, select, button, a')) {
                return;
            }

            window.location.href = row.dataset.href;

        });

    });

});
</script>

@endpush