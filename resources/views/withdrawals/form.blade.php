@extends('layouts.app')

@section('title',
    $mode === 'create'
        ? 'Заявка на выплату д/с — SuperPart'
        : 'Заявка на выплату д/с №' . $withdrawal->id . ' — SuperPart'
)

@section('breadcrumbs')
    <x-breadcrumbs :items="array_filter([
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Заявки на вывод д/с', 'url' => route('withdrawals.index')],
        ['label' => $mode === 'create'
            ? 'Заявка на вывод д/с'
            : 'Заявка на вывод д/с №' . $withdrawal->id,
           'url' => null],
    ])" />
@endsection

@section('content')
    @php
        $statusLabels = [
            'in_work'   => 'На рассмотрении',
            'completed' => 'Выполнен',
            'rejected'  => 'Отклонено',
        ];
        $batchWithdrawals = $batchWithdrawals ?? collect();
        $batchItems = $batchItems ?? collect();
        $showPartnerColumn = $showPartnerColumn ?? false;
        $showItems = $mode === 'show'
            ? ($batchItems->isNotEmpty() ? $batchItems : $withdrawal->items)
            : collect();

        $pageTitle = $mode === 'create'
            ? 'Заявка на выплату д/с'
            : 'Заявка на выплату д/с №' . $withdrawal->id . ' - ' . ($statusLabels[$withdrawal->status] ?? $withdrawal->status);

        if ($mode === 'show' && $batchWithdrawals->count() > 1) {
            $batchIds = $batchWithdrawals->pluck('id');
            $pageTitle = 'Заявка на выплату д/с №'.$batchIds->min().'–'.$batchIds->max()
                .' — '.$batchWithdrawals->sum(fn ($item) => (float) $item->total_amount).' ₽';
        }
    @endphp

    <x-page-header :title="$pageTitle" />

    @if ($errors->any())
        <x-ui.alert type="error">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert type="success">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    <x-ui.card padding="md">
        @if ($mode === 'create')
            <form method="POST" action="{{ route('withdrawals.store') }}" id="withdrawal-form">
                @csrf
        @endif

        @if ($mode === 'create')
            <x-ui.button
                type="submit"
                form="withdrawal-form"
                variant="primary"
                size="lg"
                class="mb-6 border border-primary"
                id="withdrawal-submit"
            >
                Подтвердить выплату
            </x-ui.button>
        @endif

        <div class="mb-4">
            <label class="block text-muted-foreground text-sm mb-1.5">Реквизиты (карта / ИП)</label>
            @if ($mode === 'create')
                <x-ui.select name="bank_card_id" id="withdrawal-bank-card">
                    <option value="">Выбрать сохранённые реквизиты…</option>
                    @foreach ($bankCards as $card)
                        <option
                            value="{{ $card->id }}"
                            data-requisites="{{ e($card->requisitesText()) }}"
                            @selected((string) old('bank_card_id') === (string) $card->id)
                        >
                            {{ $card->selectLabel() }}
                        </option>
                    @endforeach
                </x-ui.select>
                @if ($bankCards->isEmpty())
                    <p class="mt-1 text-xs text-muted-foreground">
                        <a href="{{ route('settings.index') }}" class="text-primary hover:underline">Добавьте карту или ИП-реквизиты в настройках</a>.
                    </p>
                @endif
            @else
                <div class="w-full bg-white border border-border text-foreground text-sm rounded-md px-3 py-2.5 dark:bg-white dark:text-slate-900">
                    <span class="{{ $withdrawal->bank_card ? '' : 'text-muted-foreground' }}">
                        {{ $withdrawal->bank_card ?: 'Не указана' }}
                    </span>
                </div>
            @endif
        </div>

        @if ($mode === 'show' && $withdrawal->recipient_birth_date)
            <div class="mb-4">
                <label class="block text-muted-foreground text-sm mb-1.5">Дата рождения получателя</label>
                <div class="w-full bg-white border border-border text-foreground text-sm rounded-md px-3 py-2.5 font-medium dark:bg-white dark:text-slate-900">
                    {{ $withdrawal->recipient_birth_date->format('d.m.Y') }}
                </div>
            </div>
        @endif

        @if ($mode === 'show' && $batchWithdrawals->count() > 1)
            <div class="mb-4 rounded-xl border border-border bg-muted/20 p-4 text-sm text-foreground">
                Одна заявка на выплату, сумма
                <span class="font-semibold">{{ number_format($batchWithdrawals->sum(fn ($item) => (float) $item->total_amount), 0, ',', ' ') }} ₽</span>.
            </div>
        @endif

        @if ($mode === 'show' && auth()->user()->hasElevatedAccess() && $withdrawal->user?->legal_form)
            <div class="mb-4 rounded-xl border border-border bg-muted/20 p-4">
                <p class="text-sm font-medium text-foreground mb-2">Данные партнёра ({{ $withdrawal->user->legalFormLabel() }})</p>
                <dl class="grid gap-2 text-sm md:grid-cols-2">
                    <div><span class="text-muted-foreground">Наименование:</span> {{ $withdrawal->user->legal_name }}</div>
                    <div><span class="text-muted-foreground">ИНН:</span> {{ $withdrawal->user->inn }}</div>
                    <div><span class="text-muted-foreground">ОГРН:</span> {{ $withdrawal->user->ogrn }}</div>
                    @if ($withdrawal->user->legal_address)
                        <div class="md:col-span-2"><span class="text-muted-foreground">Адрес:</span> {{ $withdrawal->user->legal_address }}</div>
                    @endif
                </dl>
            </div>
        @endif

        <div class="{{ $mode === 'show' ? 'mb-4' : 'mb-1' }}">
            <label class="block text-muted-foreground text-sm mb-1.5">Реквизиты для вывода</label>
            @if ($mode === 'create')
                <x-ui.textarea name="requisites" id="withdrawal-requisites" rows="4" placeholder="Выберите карту или укажите реквизиты вручную…">{{ old('requisites') }}</x-ui.textarea>
            @else
                <div class="w-full bg-white border border-border text-foreground text-sm rounded-md px-3 py-2.5 min-h-[100px] whitespace-pre-wrap dark:bg-white dark:text-slate-900">{{ $withdrawal->requisites ?: '—' }}</div>
            @endif
        </div>

        @if ($mode === 'create')
            <div class="mb-4">
                <p class="text-destructive text-xs">Указывайте ФИО и дату рождения получателя</p>
                <p class="text-destructive text-xs">Обязательно указывайте банк</p>
            </div>
        @endif

        <div class="mb-6">
            <label class="block text-muted-foreground text-sm mb-1.5">Комментарий</label>
            @if ($mode === 'create')
                <x-ui.textarea name="comment" rows="3" placeholder="">{{ old('comment') }}</x-ui.textarea>
            @else
                <div class="w-full bg-white border border-border text-foreground text-sm rounded-md px-3 py-2.5 min-h-[80px] whitespace-pre-wrap dark:bg-white dark:text-slate-900">{{ $withdrawal->comment ?: '—' }}</div>
            @endif
        </div>

        @if ($mode === 'show')
            <div class="mb-6">
                <label class="block text-muted-foreground text-sm mb-1.5">Планируемая дата инкаса</label>
                <div class="w-full bg-white border border-border text-foreground text-sm rounded-md px-3 py-2.5 dark:bg-white dark:text-slate-900">
                    {{ $withdrawal->planned_collect_at?->format('d.m.Y') ?: 'Не указана' }}
                </div>
            </div>
        @endif

        @if ($mode === 'show' && auth()->user()->hasElevatedAccess())
            @foreach (($batchWithdrawals->isNotEmpty() ? $batchWithdrawals : collect([$withdrawal])) as $statusWithdrawal)
                <div class="mb-6 rounded-xl border border-border bg-muted/20 p-4">
                    <form method="POST" action="{{ route('withdrawals.update-status', $statusWithdrawal->id) }}">
                        @csrf
                        @method('PATCH')

                        @if ($batchWithdrawals->count() > 1)
                            <p class="mb-3 text-sm text-muted-foreground">
                                Часть №{{ $statusWithdrawal->id }}
                                · {{ $statusWithdrawal->user?->name ?? ('#'.$statusWithdrawal->user_id) }}
                                · {{ number_format((float) $statusWithdrawal->total_amount, 0, ',', ' ') }} ₽
                            </p>
                        @endif

                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[240px]">
                                <label class="block text-muted-foreground text-sm mb-1.5">Статус выплаты</label>
                                <x-ui.select name="status">
                                    <option value="in_work" @selected($statusWithdrawal->status === 'in_work')>
                                        На рассмотрении
                                    </option>
                                    <option value="completed" @selected($statusWithdrawal->status === 'completed')>
                                        Выполнена
                                    </option>
                                    <option value="rejected" @selected($statusWithdrawal->status === 'rejected')>
                                        Отклонена
                                    </option>
                                </x-ui.select>
                            </div>

                            <div class="min-w-[220px]">
                                <label class="block text-muted-foreground text-sm mb-1.5">Дата инкаса</label>
                                <x-ui.input
                                    type="date"
                                    name="planned_collect_at"
                                    value="{{ old('planned_collect_at', $statusWithdrawal->planned_collect_at?->format('Y-m-d')) }}"
                                />
                            </div>

                            <x-ui.button type="submit" variant="primary" size="md">
                                Сохранить
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            @endforeach
        @endif

        @if ($mode === 'create')
            </form>
        @endif
    </x-ui.card>

    <div class="mt-6">
        <h2 class="text-lg font-semibold text-foreground mb-4">
            {{ $mode === 'create' ? 'Выберите заявки для вывода денежных средств:' : 'Заявки в этой выплате' }}
        </h2>
        @if ($mode === 'create')
            <p class="mb-4 text-sm text-muted-foreground">
                Здесь только начисления после периода охлаждения 36 часов, которые ещё не попали в другую выплату.
            </p>
        @endif

        @if ($mode === 'create')
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('withdrawals.create') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px]">
                        <label class="block text-muted-foreground text-sm mb-1.5">Источник</label>
                        <x-ui.select name="filter_source" size="sm" onchange="this.form.submit()">
                            <option value="">Все источники</option>
                            @foreach (($sourceOptions ?? []) as $sourceKey => $sourceName)
                                <option value="{{ $sourceKey }}" @selected(($filterSource ?? '') === (string) $sourceKey)>
                                    {{ $sourceName }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    @if (! empty($filterSource))
                        <a href="{{ route('withdrawals.create') }}" class="text-sm text-primary hover:underline pb-2">Сбросить</a>
                    @endif
                </form>

                <div class="text-sm text-muted-foreground">
                    Доступно к выводу (фильтр):
                    <span class="font-semibold text-foreground">
                        {{ number_format($transactions->sum('amount'), 0, ',', ' ') }} ₽
                    </span>
                    · заявок: {{ $transactions->count() }}
                </div>
            </div>

            @if (! empty($sourceTotals))
                <div class="mb-4 overflow-x-auto rounded-xl border border-border bg-muted/10 p-3">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Суммы по источникам (доступно к выводу)</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sourceTotals as $sourceName => $total)
                            <span class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs text-foreground">
                                <span class="text-muted-foreground">{{ $sourceName }}</span>
                                <span class="font-semibold">{{ number_format($total, 0, ',', ' ') }} ₽</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        <x-ui.card padding="none">
            <table class="table-sticky w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap w-12">№</th>
                        @if ($mode === 'create')
                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap w-12">
                                <input type="checkbox" id="select-all"
                                       class="w-4 h-4 accent-primary cursor-pointer rounded">
                            </th>
                        @else
                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap w-12"></th>
                        @endif
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Начисление</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Заявка</th>
                        @if ($showPartnerColumn)
                            <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Партнёр</th>
                        @endif
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Начислено</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Город</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Источник</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Закрыто (лок)</th>
                        <th class="px-3 py-3 text-muted-foreground font-normal whitespace-nowrap">Статус</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($mode === 'create')
                        @forelse ($transactions as $index => $transaction)
                            <tr class="border-b border-border/60 {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">
                                <td class="px-3 py-3 text-foreground">{{ $index + 1 }}</td>
                                <td class="px-3 py-3">
                                    <input type="checkbox" name="transaction_ids[]"
                                           value="{{ $transaction->id }}"
                                           form="withdrawal-form"
                                           data-amount="{{ (float) $transaction->amount }}"
                                           class="w-4 h-4 accent-primary cursor-pointer rounded transaction-checkbox"
                                           {{ in_array($transaction->id, old('transaction_ids', [])) ? 'checked' : '' }}>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ $transaction->id }}</td>
                                <td class="px-3 py-3 text-foreground">{{ $transaction->order_id }}</td>
                                @if ($showPartnerColumn)
                                    <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                        {{ $transaction->user?->name ?? ('#'.$transaction->user_id) }}
                                    </td>
                                @endif
                                <td class="px-3 py-3 text-foreground">{{ number_format($transaction->amount, 0, ',', ' ') }} Р</td>
                                <td class="px-3 py-3 text-foreground">{{ $transaction->order->city->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ $transaction->order?->sourceDisplayName() ?? '—' }}</td>
                                <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $transaction->order?->closed_local?->format('d.m.Y, H:i') ?? $transaction->completed_at?->format('d.m.Y, H:i') ?? '—' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="inline-block px-2.5 py-1 text-xs font-medium rounded whitespace-nowrap bg-emerald-500 text-white">
                                        Ожидает выплаты
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-muted-foreground">
                                    Нет начислений, доступных для вывода. Заявки младше 36 часов после закрытия сюда не попадают.
                                </td>
                            </tr>
                        @endforelse
                    @else
                        @forelse ($showItems as $index => $item)
                            <tr class="border-b border-border/60 {{ $index % 2 === 0 ? 'bg-card' : 'bg-muted/20' }}">
                                <td class="px-3 py-3 text-foreground">{{ $index + 1 }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-block w-4 h-4 bg-primary rounded-sm text-center text-primary-foreground text-xs leading-4">✓</span>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ $item->transaction_id }}</td>
                                <td class="px-3 py-3 text-foreground">{{ $item->order_id }}</td>
                                @if ($showPartnerColumn)
                                    <td class="px-3 py-3 text-foreground whitespace-nowrap">
                                        {{ $item->transaction?->user?->name ?? ('#'.($item->transaction?->user_id ?? '—')) }}
                                    </td>
                                @endif
                                <td class="px-3 py-3 text-foreground">{{ number_format($item->amount, 0, ',', ' ') }} Р</td>
                                <td class="px-3 py-3 text-foreground">{{ $item->city->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ $item->order?->sourceDisplayName() ?? '—' }}</td>
                                <td class="px-3 py-3 text-foreground whitespace-nowrap">{{ $item->order?->closed_local?->format('d.m.Y, H:i') ?? $item->created_local?->format('d.m.Y, H:i') ?? '—' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="inline-block px-2.5 py-1 text-xs font-medium rounded whitespace-nowrap bg-emerald-500 text-white">
                                        Ожидает выплаты
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-muted-foreground">
                                    Нет данных
                                </td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>

            @if ($mode === 'create' && $transactions->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-muted/20 px-4 py-3">
                    <div class="text-sm text-muted-foreground">
                        Выбрано заявок:
                        <span id="withdrawal-selected-count" class="font-semibold text-foreground">0</span>
                    </div>
                    <div class="text-sm text-foreground">
                        Сумма выбранных:
                        <span id="withdrawal-selected-sum" class="text-lg font-bold text-primary">0 ₽</span>
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cardSelect = document.getElementById('withdrawal-bank-card');
    const requisites = document.getElementById('withdrawal-requisites');

    cardSelect?.addEventListener('change', () => {
        const option = cardSelect.options[cardSelect.selectedIndex];
        if (!requisites || !option) return;

        const text = option.dataset.requisites || '';
        if (text) {
            requisites.value = text;
        }
    });

    if (cardSelect?.value && requisites && !requisites.value) {
        cardSelect.dispatchEvent(new Event('change'));
    }

    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.transaction-checkbox');
    const sumEl = document.getElementById('withdrawal-selected-sum');
    const countEl = document.getElementById('withdrawal-selected-count');

    const formatMoney = (value) =>
        new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(value) + ' ₽';

    const updateSelectedSum = () => {
        let sum = 0;
        let count = 0;

        checkboxes.forEach((cb) => {
            if (!cb.checked) return;
            sum += Number(cb.dataset.amount || 0);
            count += 1;
        });

        if (sumEl) sumEl.textContent = formatMoney(sum);
        if (countEl) countEl.textContent = String(count);

        if (selectAll && checkboxes.length) {
            selectAll.checked = [...checkboxes].every((c) => c.checked);
            selectAll.indeterminate = !selectAll.checked && [...checkboxes].some((c) => c.checked);
        }
    };

    selectAll?.addEventListener('change', () => {
        checkboxes.forEach((cb) => {
            cb.checked = selectAll.checked;
        });
        updateSelectedSum();
    });

    checkboxes.forEach((cb) => {
        cb.addEventListener('change', updateSelectedSum);
    });

    updateSelectedSum();

    const form = document.getElementById('withdrawal-form');
    form?.addEventListener('submit', (event) => {
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        const submitBtn = document.getElementById('withdrawal-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-60');
        }
    });
});
</script>
@endpush