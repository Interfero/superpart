@extends('layouts.app')

@section('title', 'Синхронизация CRM — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Синхронизация', 'url' => null],
    ]" />
@endsection

@section('content')
<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">
    <div class="px-6 py-7">
        <h1 class="text-3xl font-bold text-foreground">Мониторинг синхронизации</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
            Только метрики интеграции Lead Control → SuperPart. Персональные данные заявок сюда не выводятся.
        </p>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
    <x-ui.card class="p-4">
        <div class="text-xs text-muted-foreground">Состояние</div>
        <div class="mt-1 text-xl font-semibold">{{ $health['status'] ?? 'нет данных' }}</div>
        <div class="mt-1 text-xs text-muted-foreground">{{ $health['checked_at'] ?? '—' }}</div>
    </x-ui.card>
    <x-ui.card class="p-4">
        <div class="text-xs text-muted-foreground">Курсор сверки</div>
        <div class="mt-1 text-xl font-semibold">{{ $cursor ?? '0' }}</div>
    </x-ui.card>
    <x-ui.card class="p-4">
        <div class="text-xs text-muted-foreground">Inbox pending / rejected</div>
        <div class="mt-1 text-xl font-semibold">{{ $inbox['pending'] }} / {{ $inbox['rejected'] }}</div>
        <div class="mt-1 text-xs text-muted-foreground">возраст pending: {{ $inbox['oldest_pending_age_sec'] }} с</div>
    </x-ui.card>
    <x-ui.card class="p-4">
        <div class="text-xs text-muted-foreground">Dead-letter / расхождения / исключено</div>
        <div class="mt-1 text-xl font-semibold">{{ $deadLetter }} / {{ $discrepancies }} / {{ $excluded }}</div>
    </x-ui.card>
</div>

<div class="grid gap-4 lg:grid-cols-2 mb-6">
    <x-ui.card class="p-4">
        <h2 class="text-lg font-semibold mb-3">Последняя полная сверка</h2>
        @if ($full)
            <dl class="grid grid-cols-2 gap-2 text-sm">
                @foreach ($full as $key => $value)
                    <dt class="text-muted-foreground">{{ $key }}</dt>
                    <dd>{{ is_scalar($value) || $value === null ? (string) ($value ?? '—') : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                @endforeach
            </dl>
        @else
            <p class="text-sm text-muted-foreground">Ещё не запускалась.</p>
        @endif
    </x-ui.card>
    <x-ui.card class="p-4">
        <h2 class="text-lg font-semibold mb-3">Inbox</h2>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="text-muted-foreground">applied</dt><dd>{{ $inbox['applied'] }}</dd>
            <dt class="text-muted-foreground">duplicate/stale</dt><dd>{{ $inbox['duplicate'] }}</dd>
            <dt class="text-muted-foreground">pending</dt><dd>{{ $inbox['pending'] }}</dd>
            <dt class="text-muted-foreground">rejected</dt><dd>{{ $inbox['rejected'] }}</dd>
        </dl>
    </x-ui.card>
</div>

<x-ui.card class="p-4 mb-6">
    <h2 class="text-lg font-semibold mb-3">Прогоны сверки</h2>
    @if ($runs->isEmpty())
        <p class="text-sm text-muted-foreground">Записей нет.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-muted-foreground">
                        <th class="py-2 pr-3">id</th>
                        <th class="py-2 pr-3">вид</th>
                        <th class="py-2 pr-3">статус</th>
                        <th class="py-2 pr-3">checked</th>
                        <th class="py-2 pr-3">equal</th>
                        <th class="py-2 pr-3">repaired</th>
                        <th class="py-2 pr-3">excluded</th>
                        <th class="py-2 pr-3">finance</th>
                        <th class="py-2 pr-3">failed</th>
                        <th class="py-2 pr-3">финиш</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td class="py-1 pr-3">{{ $run->id }}</td>
                            <td class="py-1 pr-3">{{ $run->kind }}{{ $run->dry_run ? ' dry' : '' }}</td>
                            <td class="py-1 pr-3">{{ $run->status }}</td>
                            <td class="py-1 pr-3">{{ $run->checked }}</td>
                            <td class="py-1 pr-3">{{ $run->equal_count }}</td>
                            <td class="py-1 pr-3">{{ $run->repaired }}</td>
                            <td class="py-1 pr-3">{{ $run->excluded }}</td>
                            <td class="py-1 pr-3">{{ $run->financial_discrepancy }}</td>
                            <td class="py-1 pr-3">{{ $run->failed }}</td>
                            <td class="py-1 pr-3">{{ $run->finished_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>

<x-ui.card class="p-4">
    <h2 class="text-lg font-semibold mb-3">История источников заявок</h2>
    @if ($history->isEmpty())
        <p class="text-sm text-muted-foreground">Записей нет.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-muted-foreground">
                        <th class="py-2 pr-3">заказ</th>
                        <th class="py-2 pr-3">с РК</th>
                        <th class="py-2 pr-3">на РК</th>
                        <th class="py-2 pr-3">было SP</th>
                        <th class="py-2 pr-3">стало SP</th>
                        <th class="py-2 pr-3">причина</th>
                        <th class="py-2 pr-3">версия</th>
                        <th class="py-2 pr-3">время</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $row)
                        <tr>
                            <td class="py-1 pr-3">{{ $row->order_id }}</td>
                            <td class="py-1 pr-3">{{ $row->from_source_id ?? '—' }}</td>
                            <td class="py-1 pr-3">{{ $row->to_source_id ?? '—' }}</td>
                            <td class="py-1 pr-3">{{ $row->from_eligible === null ? '—' : ($row->from_eligible ? 'да' : 'нет') }}</td>
                            <td class="py-1 pr-3">{{ $row->to_eligible === null ? '—' : ($row->to_eligible ? 'да' : 'нет') }}</td>
                            <td class="py-1 pr-3">{{ $row->reason }}</td>
                            <td class="py-1 pr-3">{{ $row->event_version }}</td>
                            <td class="py-1 pr-3">{{ $row->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>
@endsection
