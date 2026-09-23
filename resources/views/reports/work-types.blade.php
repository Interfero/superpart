@extends('layouts.app')

@section('title', 'Отчёт по видам работ — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Отчёт по видам работ', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">
        <div class="px-6 py-7">
            <h1 class="text-3xl font-bold text-foreground">Отчёт по видам работ</h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Детализация заявок с фильтрами по фактическому виду работы и источнику.
            </p>
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-ui.card>
            <div class="text-sm text-muted-foreground">Заявок</div>
            <div class="mt-2 text-3xl font-bold text-foreground">{{ $totals['orders_count'] }}</div>
        </x-ui.card>
        <x-ui.card>
            <div class="text-sm text-muted-foreground">Закрыто</div>
            <div class="mt-2 text-3xl font-bold text-green-500">{{ $totals['closed_count'] }}</div>
        </x-ui.card>
        <x-ui.card>
            <div class="text-sm text-muted-foreground">Начислено</div>
            <div class="mt-2 text-3xl font-bold text-primary">
                {{ number_format($totals['total_charge'], 0, ',', ' ') }} ₽
            </div>
        </x-ui.card>
    </div>

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="px-4 pt-4">
            <x-date-filter
                action="{{ route('reports.work-types') }}"
                :dateFrom="request('date_from')"
                :dateTo="request('date_to')"
            >
                <div>
                    <label class="mb-1 block text-xs text-muted-foreground">Вид работы</label>
                    <x-ui.select name="equipment_type" class="min-w-[260px]">
                        <option value="">Все виды работ</option>
                        @foreach ($equipmentTypes as $code => $label)
                            <option value="{{ $code }}" @selected(request('equipment_type') === $code)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-muted-foreground">Источник</label>
                    <x-ui.select name="filter_source" class="min-w-[220px]">
                        <option value="">Все источники</option>
                        @foreach ($sourceOptions as $sourceKey => $sourceName)
                            <option value="{{ $sourceKey }}" @selected(request('filter_source') === $sourceKey)>{{ $sourceName }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </x-date-filter>
        </div>

        <div class="overflow-x-auto">
            <table class="table-sticky w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-card">
                    <tr class="border-b border-border bg-card/95 backdrop-blur">
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">ID заявки</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Город</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Статус</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Тип</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Нас. пункт, адрес</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Источник</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Время заявки</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Имя</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Телефон</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Создано лок</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Закрыто</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap text-right">Начисление</th>
                        <th class="px-3 py-3 font-normal text-muted-foreground whitespace-nowrap">Сотрудник</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $typeLabels = ['first_time' => 'Впервые', 'warranty' => 'Гарантия', 'repeat' => 'Повтор'];
                    @endphp
                    @forelse ($orders as $index => $order)
                        <tr class="cursor-pointer border-b border-border/60 transition hover:bg-primary/5 {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}"
                            data-href="{{ route('orders.show', $order->id) }}">
                            <td class="px-3 py-3 font-medium text-foreground whitespace-nowrap">{{ $order->id }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->city?->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap"><x-status-badge :status="$order->status" type="order" /></td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $typeLabels[$order->type] ?? $order->type }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ implode(', ', array_filter([$order->settlement, $order->address])) ?: '—' }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->sourceDisplayName() }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->order_time?->format('d.m.Y, H:i') ?? '—' }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->client_name }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->client_phone }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->created_local?->format('d.m.Y, H:i') ?? '—' }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->closed_local?->format('d.m.Y, H:i') ?? '—' }}</td>
                            <td class="px-3 py-3 text-right font-semibold text-foreground whitespace-nowrap">{{ $order->charge_amount > 0 ? number_format($order->charge_amount, 0, ',', ' ').' ₽' : '—' }}</td>
                            <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $order->user?->displayFullName() ?: ($order->employee?->name ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-4 py-10 text-center text-muted-foreground">Заявки не найдены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-4">
            <div class="text-sm text-muted-foreground">Найдено заявок: <span class="font-semibold text-foreground">{{ $orders->total() }}</span></div>
            @if ($orders->hasPages())
                {{ $orders->links() }}
            @endif
        </div>
    </x-ui.card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('tr[data-href]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (!event.target.closest('a, button, input, select, label')) {
                window.location.href = row.dataset.href;
            }
        });
    });
});
</script>
@endpush
