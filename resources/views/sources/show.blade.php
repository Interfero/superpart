@extends('layouts.app')

@php
    $pageTitle = old('name', $source->name) . ' — Источник — SuperPart';
@endphp

@section('title', $pageTitle)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Источники', 'url' => route('sources.index')],
        ['label' => old('name', $source->name), 'url' => null],
    ]" />
@endsection

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="flex flex-col gap-6 px-6 py-7 lg:flex-row lg:items-center lg:justify-between">

        <div>

            <h1 class="text-3xl font-bold text-foreground">
                {{ old('name', $source->name) }}
            </h1>

            <p class="mt-2 text-sm text-muted-foreground">
                @if (!empty($canManageAll))
                    Карточка источника партнёра. Изменения сохраняются в SuperPart и передаются в CRM.
                @else
                    Карточка источника. Изменения передаются в CRM.
                @endif
            </p>

        </div>

        <div class="flex flex-wrap gap-3">

            <x-ui.button
                tag="a"
                :href="route('sources.index')"
                variant="secondary"
                size="lg">

                К списку

            </x-ui.button>

        </div>

    </div>

</div>

@if ($errors->has('delete'))

    <x-ui.alert type="error" class="mb-4">
        {{ $errors->first('delete') }}
    </x-ui.alert>

@endif

@if (session('success'))

    <x-ui.alert type="success" class="mb-4">
        {{ session('success') }}
    </x-ui.alert>

@endif

<div class="grid gap-4 mb-6 md:grid-cols-3">

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            ID CRM
        </div>

        <div class="mt-2 text-3xl font-bold text-foreground tabular-nums">
            @php $crmId = $source->crmDisplayId(); @endphp
            @if ($crmId)
                #{{ $crmId }}
            @else
                <span class="text-muted-foreground text-lg">ещё нет в CRM</span>
            @endif
        </div>

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Тип источника
        </div>

        <div class="mt-2 text-lg font-semibold text-foreground">
            {{ $source->typeLabel() }}
        </div>

        @if ($source->has_review)
            <p class="mt-1 text-xs text-muted-foreground">
                Доступен в разделе работы с отзывами
            </p>
        @endif

    </x-ui.card>

    <x-ui.card>

        <div class="text-sm text-muted-foreground">
            Статус
        </div>

        <div class="mt-2 text-3xl font-bold text-primary">
            Активен
        </div>

    </x-ui.card>

</div>

@if (!empty($canManageAll) && isset($assignedPartners))
    <x-ui.card class="mb-6 max-w-4xl">
        <h2 class="text-lg font-semibold text-foreground">Партнёры с доступом</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Партнёры, которым назначен этот источник (видят заказы по нему).
        </p>
        @if ($assignedPartners->isEmpty())
            <p class="mt-3 text-sm text-muted-foreground">Пока никому не назначен. Назначьте в карточке партнёра.</p>
        @else
            <ul class="mt-3 space-y-2">
                @foreach ($assignedPartners as $partner)
                    <li>
                        <a href="{{ route('management.users.edit', $partner) }}" class="text-primary hover:underline">
                            {{ $partner->name }}
                        </a>
                        <span class="text-muted-foreground text-sm">({{ $partner->email }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endif

<div class="max-w-4xl">

    <x-ui.card padding="lg" class="shadow-sm">

        @can('manage-sources')

            <form
                id="source-update-form"
                method="POST"
                action="{{ route('sources.update', $source->id) }}"
                class="space-y-6">

                @csrf
                @method('PUT')

                @if (!empty($canManageAll) && ($partners ?? collect())->isNotEmpty())
                    <x-ui.form-group label="Партнёр-владелец" name="owner_user_id" class="!mb-0" hint="Необязательно. Доступ нескольким партнёрам — в «Источники партнёров».">
                        <x-ui.select id="owner_user_id" name="owner_user_id" :error="$errors->has('owner_user_id')">
                            <option value="">— Без смены партнёра —</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->id }}" @selected((string) old('owner_user_id', $source->user_id) === (string) $partner->id)>
                                    {{ $partner->name }} ({{ $partner->email }})
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.form-group>
                @endif

                <x-ui.form-group
                    label="Название"
                    name="name"
                    required
                    class="!mb-0">

                    <x-ui.input
                        id="name"
                        name="name"
                        value="{{ old('name', $source->name) }}"
                        required
                        placeholder="Название источника"
                        :error="$errors->has('name')"
                    />

                </x-ui.form-group>

                <x-ui.form-group
                    label="Тип источника"
                    name="source_type"
                    required
                    class="!mb-0"
                    hint="Для рекламной площадки источник автоматически доступен в разделе работы с отзывами.">

                    <x-ui.select
                        id="source_type"
                        name="source_type"
                        required
                        :error="$errors->has('source_type')"
                    >
                        @foreach (\App\Models\Source::typeLabels() as $value => $label)
                            <option value="{{ $value }}" @selected(old('source_type', $source->source_type) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui.select>

                </x-ui.form-group>

                <x-ui.form-group
                    label="Комментарий"
                    name="comment"
                    class="!mb-0">

                    <x-ui.textarea
                        id="comment"
                        name="comment"
                        rows="4"
                        placeholder="Комментарий (необязательно)"
                        :error="$errors->has('comment')"
                    >{{ old('comment', $source->comment) }}</x-ui.textarea>

                </x-ui.form-group>

                <x-ui.form-group
                    label="Ссылка"
                    name="review_url"
                    class="!mb-0"
                    hint="Ссылка на аккаунт или сайт — необязательно.">

                    <x-ui.input
                        id="review_url"
                        name="review_url"
                        type="url"
                        value="{{ old('review_url', $source->review_url) }}"
                        placeholder="https://..."
                        :error="$errors->has('review_url')"
                    />

                </x-ui.form-group>

            </form>

            <div class="flex flex-wrap items-center gap-3 mt-6 pt-6 border-t border-border">

                <x-ui.button
                    type="submit"
                    form="source-update-form"
                    variant="primary"
                    size="lg">

                    Сохранить

                </x-ui.button>

                <x-ui.button
                    tag="a"
                    :href="route('sources.index')"
                    variant="secondary"
                    size="lg">

                    К списку

                </x-ui.button>

                <form
                    method="POST"
                    action="{{ route('sources.destroy', $source->id) }}"
                    class="inline"
                    onsubmit="return confirm('Удалить источник? Он скроется из списков, данные в базе сохранятся.');"
                >

                    @csrf
                    @method('DELETE')

                    <x-ui.button
                        type="submit"
                        variant="destructive"
                        size="lg">

                        Удалить

                    </x-ui.button>

                </form>

            </div>

        @else

            <div class="space-y-6">

                <div>

                    <div class="text-sm text-muted-foreground mb-1">
                        Название
                    </div>

                    <div class="text-foreground text-lg font-semibold">
                        {{ $source->name }}
                    </div>

                </div>

                <div>

                    <div class="text-sm text-muted-foreground mb-1">
                        Тип источника
                    </div>

                    <div class="text-foreground font-semibold">
                        {{ $source->typeLabel() }}
                    </div>

                    @if ($source->has_review)
                        <p class="mt-1 text-sm text-muted-foreground">
                            Доступен в разделе работы с отзывами
                        </p>
                    @endif

                </div>

                <div>

                    <div class="text-sm text-muted-foreground mb-1">
                        Комментарий
                    </div>

                    <div class="text-foreground whitespace-pre-wrap">
                        {{ $source->comment ?: '—' }}
                    </div>

                </div>

                @if ($source->review_url)
                    <div>

                        <div class="text-sm text-muted-foreground mb-1">
                            Ссылка
                        </div>

                        <a href="{{ $source->review_url }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline break-all">
                            {{ $source->review_url }}
                        </a>

                    </div>
                @endif

                <div class="pt-4 border-t border-border">

                    <x-ui.button
                        tag="a"
                        :href="route('sources.index')"
                        variant="secondary"
                        size="lg">

                        К списку

                    </x-ui.button>

                </div>

            </div>

        @endcan

    </x-ui.card>

</div>

@endsection
