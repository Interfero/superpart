@extends('layouts.app')

@section('title', "Заявка №{$order->displayNumber()} — SuperPart")

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Список заявок', 'url' => route('orders.index')],
        ['label' => 'Заявка №' . $order->displayNumber(), 'url' => null],
    ]" />
@endsection

@section('content')
    <!-- superpart:order-detail:20260609 -->

    <x-page-header :title="'Заявка №' . $order->displayNumber()" />

    @if (session('status'))
        <x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert type="error" class="mb-4">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @php
        $typeLabels = ['first_time' => 'Впервые', 'first' => 'Впервые', 'warranty' => 'Гарантия', 'repeat' => 'Повтор'];
        $syncLabels = [
            'pending' => 'ожидает отправки',
            'synced' => 'отправлено в CRM',
            'error' => 'не удалось отправить в CRM',
        ];
        $visitTimeLabel = \App\Support\VisitDateTime::formatInCity($order->city, $order->order_time);
    @endphp

    <x-ui.card padding="md">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">

            <div class="space-y-4">
                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">ID заявки:</span>
                    <span class="text-foreground font-medium">{{ $order->displayNumber() }}</span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Синхронизация:</span>
                    <span class="text-foreground">
                        @if ($order->sync_status)
                            {{ $syncLabels[$order->sync_status] ?? $order->sync_status }}
                        @else
                            <span class="text-muted-foreground">—</span>
                        @endif
                    </span>
                </div>

                @if ($canRetrySync)
                    <div class="flex items-baseline gap-3">
                        <span class="text-muted-foreground text-sm w-44 shrink-0"></span>
                        <form method="POST" action="{{ route('orders.retry-sync', $order->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="outline" size="sm">
                                Повторить отправку в CRM
                            </x-ui.button>
                        </form>
                    </div>
                @endif

                <div class="flex items-center gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Статус:</span>
                    <x-status-badge :status="$order->status" type="order" />
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Вид:</span>
                    <span class="text-foreground">{{ $typeLabels[$order->type] ?? $order->type }}</span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Профильность:</span>
                    <span class="text-foreground">{{ $order->is_non_profile ? 'Непрофильный' : 'Профильный' }}</span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Город:</span>
                    <span class="text-foreground">{{ $order->city->name ?? '—' }}</span>
                </div>

                @if ($order->settlement || $order->address)
                    <div class="flex items-baseline gap-3">
                        <span class="text-muted-foreground text-sm w-44 shrink-0">Населённый пункт, адрес:</span>
                        <span class="text-foreground">{{ implode(', ', array_filter([$order->settlement, $order->address])) }}</span>
                    </div>
                @endif

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Вид работ:</span>
                    <span class="text-foreground">
                        @if ($order->equipment_type)
                            {{ \App\Support\OrderEquipment::label($order->equipment_type) }}
                        @else
                            {{ $order->workType->name ?? '—' }}
                        @endif
                    </span>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Имя клиента:</span>
                    <span class="text-foreground">{{ $order->client_name }}</span>
                </div>

                @if ($order->client_age !== null)
                    <div class="flex items-baseline gap-3">
                        <span class="text-muted-foreground text-sm w-44 shrink-0">Примерный возраст:</span>
                        <span class="text-foreground">{{ $order->client_age }} лет</span>
                    </div>
                @endif

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Телефон:</span>
                    <span class="text-foreground">
                        @php
                            $p = preg_replace('/\D/', '', (string) $order->client_phone);
                            if (strlen($p) === 10) {
                                $p = '+7 (' . substr($p, 0, 3) . ') ' . substr($p, 3, 3) . '-' . substr($p, 6, 2) . '-' . substr($p, 8, 2);
                            } else {
                                $p = $order->client_phone;
                            }
                        @endphp
                        {{ $p }}
                    </span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Без звонка:</span>
                    <span class="text-foreground">{{ $order->without_call ? 'Да' : 'Нет' }}</span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Источник:</span>
                    <span class="text-foreground">{{ $order->sourceDisplayName() }}</span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Сотрудник:</span>
                    <span class="text-foreground">{{ $order->creatorDisplayName() ?: '—' }}</span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Время визита:</span>
                    <span class="text-foreground">
                        {{ $visitTimeLabel ?? '—' }}
                        @if ($order->city?->name)
                            <span class="text-xs text-muted-foreground">(местное время, {{ $order->city->name }})</span>
                        @endif
                    </span>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-muted-foreground text-sm w-44 shrink-0">Создано:</span>
                    <span class="text-foreground">{{ $order->created_local ? $order->created_local->format('d.m.Y, H:i') : '—' }}</span>
                </div>

                @if ($order->closed_local)
                    <div class="flex items-baseline gap-3">
                        <span class="text-muted-foreground text-sm w-44 shrink-0">Закрыто:</span>
                        <span class="text-foreground">{{ $order->closed_local->format('d.m.Y, H:i') }}</span>
                    </div>
                @endif
            </div>

            @if ($order->order_adds)
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-foreground mb-2">Комментарий к заявке</p>
                    <div class="text-sm text-foreground whitespace-pre-wrap border border-border rounded-md px-3 py-2 bg-muted/20">{{ $order->order_adds }}</div>
                </div>
            @endif

            @if ($order->sync_last_error)
                <div class="md:col-span-2">
                    <p class="text-xs text-muted-foreground leading-relaxed border border-border rounded-md px-3 py-2 bg-muted/20">
                        <span class="text-foreground font-medium">Причина:</span> {{ $order->sync_last_error }}
                    </p>
                </div>
            @endif
        </div>
    </x-ui.card>

    <x-ui.card padding="md" class="mt-6">
        <div class="flex items-center gap-4">
            <span class="text-muted-foreground text-sm">Сумма начисления:</span>
            <span class="text-2xl font-bold text-foreground">
                @if ($order->charge_amount > 0)
                    {{ number_format($order->charge_amount, 0, ',', ' ') }} Р
                @else
                    —
                @endif
            </span>
        </div>
    </x-ui.card>

    <x-ui.card padding="md" class="mt-6">
        <p class="text-sm font-medium text-foreground mb-3">Действия по заявке</p>
        <div class="flex flex-wrap gap-2 mb-4">
            @if ($canComment)
                <x-ui.button type="button" variant="outline" size="sm" id="toggle-order-comment">
                    Добавить комментарий
                </x-ui.button>
            @else
                <x-ui.button type="button" variant="outline" size="sm" disabled>
                    Добавить комментарий
                </x-ui.button>
            @endif

            @if ($canCancel)
                <form method="POST" action="{{ route('orders.cancel', $order->id) }}" class="inline" onsubmit="return confirm('Отменить заявку №{{ $order->id }}?');">
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="sm">
                        Отменить заявку
                    </x-ui.button>
                </form>
            @else
                <x-ui.button type="button" variant="outline" size="sm" disabled>
                    Отменить заявку
                </x-ui.button>
            @endif

            <x-ui.button
                tag="a"
                :href="route('feedback.create', ['order_id' => $order->id, 'subject' => 'КЦ', 'message' => 'Заявка №' . $order->id . ': '])"
                variant="outline"
                size="sm"
            >
                Претензия
            </x-ui.button>

            <x-ui.button
                tag="a"
                :href="route('reviews.create', ['order_id' => $order->id])"
                variant="outline"
                size="sm"
            >
                Негативный отзыв
            </x-ui.button>
        </div>

        @if ($canComment)
            <form id="order-comment-form" method="POST" action="{{ route('orders.comment', $order->id) }}" class="hidden space-y-3 border-t border-border pt-4">
                @csrf
                <x-ui.form-group label="Новый комментарий" name="comment" class="!mb-0">
                    <x-ui.textarea name="comment" rows="3" maxlength="2000" placeholder="Текст будет добавлен к комментарию заявки" :error="$errors->has('comment')">{{ old('comment') }}</x-ui.textarea>
                </x-ui.form-group>
                <div class="flex gap-2">
                    <x-ui.button type="submit" variant="primary" size="sm">Сохранить комментарий</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" id="cancel-order-comment">Отмена</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>

    <div class="mt-6">
        <x-ui.button tag="a" :href="route('orders.index')" variant="secondary" size="md" class="inline-flex gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Назад к списку
        </x-ui.button>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const toggleBtn = document.getElementById('toggle-order-comment');
            const form = document.getElementById('order-comment-form');
            const cancelBtn = document.getElementById('cancel-order-comment');

            toggleBtn?.addEventListener('click', () => {
                form?.classList.remove('hidden');
                form?.querySelector('textarea')?.focus();
            });

            cancelBtn?.addEventListener('click', () => {
                form?.classList.add('hidden');
            });

            @if ($errors->has('comment'))
                form?.classList.remove('hidden');
            @endif
        })();
    </script>
@endpush
