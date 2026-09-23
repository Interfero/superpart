@extends('layouts.app')

@section('title', 'Создать источник — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Источники', 'url' => route('sources.index')],
        ['label' => 'Создать источник', 'url' => null],
    ]" />
@endsection

@section('content')

<div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent mb-6">

    <div class="px-6 py-7">

        <h1 class="text-3xl font-bold text-foreground">
            Создать источник
        </h1>

        <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
            Создание нового источника заявок и CRM-канала.
        </p>

    </div>

</div>

<div class="max-w-3xl">

    <x-ui.card padding="lg" class="shadow-sm">

        <form method="POST"
              action="{{ route('sources.store') }}"
              class="space-y-6">

            @csrf

            <p class="text-sm leading-6 text-muted-foreground">
                Источник создаётся в SuperPart и передаётся в CRM.
            </p>

            @if (!empty($canAssignPartner) && ($partners ?? collect())->isNotEmpty())
                <x-ui.form-group label="Партнёр" name="owner_user_id" class="!mb-0" hint="Необязательно — доступ партнёрам можно назначить в «Источники партнёров».">
                    <x-ui.select id="owner_user_id" name="owner_user_id" :error="$errors->has('owner_user_id')">
                        <option value="">— Без партнёра —</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected((string) old('owner_user_id') === (string) $partner->id)>
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
                    value="{{ old('name') }}"
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
                    <option value="">Выберите тип</option>
                    @foreach (\App\Models\Source::typeLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('source_type') === $value)>
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
                >{{ old('comment') }}</x-ui.textarea>

            </x-ui.form-group>

            <x-ui.form-group
                label="Ссылка"
                name="review_url"
                class="!mb-0"
                hint="Ссылка на аккаунт или сайт (Avito и т.п.) — необязательно.">

                <x-ui.input
                    id="review_url"
                    name="review_url"
                    type="url"
                    value="{{ old('review_url') }}"
                    placeholder="https://..."
                    :error="$errors->has('review_url')"
                />

            </x-ui.form-group>

            <div class="flex flex-wrap items-center gap-3 pt-2">

                <x-ui.button
                    type="submit"
                    variant="primary"
                    size="lg">

                    Создать

                </x-ui.button>

                <x-ui.button
                    tag="a"
                    :href="route('sources.index')"
                    variant="secondary"
                    size="lg">

                    Отмена

                </x-ui.button>

            </div>

        </form>

    </x-ui.card>

</div>

@endsection
