@extends('layouts.app')

@section('title', 'Отзыв #' . $review->id . ' — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Работа с отзывами', 'url' => route('reviews.index')],
        ['label' => 'Отзыв #' . $review->id, 'url' => null],
    ]" />
@endsection

@section('content')
    @php
        $resultLabels = [
            'resolved' => 'Решено',
            'not_resolved' => 'Не решено',
            'refund' => 'Возврат',
        ];
    @endphp

    <div class="max-w-6xl mx-auto space-y-6">
        @if (session('success'))
            <x-ui.alert type="success">
                {{ session('success') }}
            </x-ui.alert>
        @endif

        <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">
            <div class="px-6 py-7">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                    <h1 class="text-3xl font-bold text-foreground">
                        Отзыв #{{ $review->id }}
                    </h1>

                    <x-ui.button
                        tag="a"
                        :href="route('reviews.index')"
                        variant="secondary"
                        size="md"
                        class="shrink-0"
                    >
                        К списку
                    </x-ui.button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                    <x-status-badge :status="$review->status" type="review" />

                    @if ($review->order_id)
                        <span>
                            Заказ:
                            <a href="{{ route('orders.show', $review->order_id) }}"
                               class="text-primary hover:underline">
                                #{{ $review->order_id }}
                            </a>
                        </span>
                    @else
                        <span>Без привязки к заявке</span>
                    @endif

                    @if ($review->city)
                        <span>
                            Город:
                            <span class="text-foreground">{{ $review->city->name }}</span>
                        </span>
                    @endif

                    <span>
                        Создан:
                        <span class="text-foreground">{{ $review->created_at?->format('d.m.Y H:i') }}</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-ui.card>
                    <h2 class="mb-4 text-lg font-semibold text-foreground">
                        Ссылка на отзыв
                    </h2>

                    @if ($review->review_url)
                        <a href="{{ $review->review_url }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="break-all text-primary underline hover:text-primary/80">
                            {{ $review->review_url }}
                        </a>
                    @else
                        <div class="text-sm text-muted-foreground">Ссылка отсутствует</div>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <h2 class="mb-4 text-lg font-semibold text-foreground">
                        Комментарий
                    </h2>

                    @if ($review->comment)
                        <div class="whitespace-pre-wrap text-sm leading-7 text-foreground">
                            {{ $review->comment }}
                        </div>
                    @else
                        <div class="text-sm text-muted-foreground">
                            Комментарий отсутствует
                        </div>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <h2 class="mb-4 text-lg font-semibold text-foreground">
                        Результат работы
                    </h2>

                    @if ($review->result)
                        <div class="inline-flex rounded-full px-3 py-1 text-sm font-semibold
                            {{ $review->result === 'resolved' ? 'bg-green-500/10 text-green-500' : '' }}
                            {{ $review->result === 'not_resolved' ? 'bg-red-500/10 text-red-500' : '' }}
                            {{ $review->result === 'refund' ? 'bg-yellow-500/10 text-yellow-500' : '' }}
                        ">
                            {{ $resultLabels[$review->result] ?? $review->result }}
                        </div>
                    @else
                        <div class="text-sm text-muted-foreground">
                            Результат ещё не заполнен
                        </div>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <h2 class="mb-4 text-lg font-semibold text-foreground">
                        Фотографии
                    </h2>

                    @if (!empty($review->photos))
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($review->photos as $photo)
                                <a href="{{ route('attachments.review-photo', ['review' => $review, 'index' => $loop->index]) }}"
                                   target="_blank"
                                   class="group block overflow-hidden rounded-xl border border-border bg-muted/20">
                                    <img
                                        src="{{ route('attachments.review-photo', ['review' => $review, 'index' => $loop->index]) }}"
                                        alt=""
                                        class="h-56 w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]"
                                    >

                                    <div class="border-t border-border px-3 py-2 text-xs text-muted-foreground truncate">
                                        {{ $photo['name'] ?? 'Фото' }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center text-sm text-muted-foreground">
                            Фотографии не прикреплены
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card>
                    <h2 class="mb-4 text-lg font-semibold text-foreground">
                        Информация
                    </h2>

                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Тип отзыва</dt>
                            <dd class="mt-1 text-foreground">
                                {{ ['kc' => 'КЦ отзыв', 'branch' => 'Филиал'][$review->review_type] ?? 'Без типа' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-muted-foreground">Статус</dt>
                            <dd class="mt-1">
                                <x-status-badge :status="$review->status" type="review" />
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Статус меняет только диспетчер в CRM.
                                </p>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-muted-foreground">Результат</dt>
                            <dd class="mt-1 text-foreground">
                                {{ $resultLabels[$review->result] ?? $review->result ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-muted-foreground">Возврат</dt>
                            <dd class="mt-1 text-foreground">
                                {{ $review->refund ? 'Да' : 'Нет' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-muted-foreground">Автор</dt>
                            <dd class="mt-1 text-foreground">
                                {{ $review->user->name ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-muted-foreground">Дата создания</dt>
                            <dd class="mt-1 text-foreground">
                                {{ $review->created_at?->format('d.m.Y H:i') ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-muted-foreground">Дата закрытия</dt>
                            <dd class="mt-1 text-foreground">
                                {{ $review->closed_at?->format('d.m.Y H:i') ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection
