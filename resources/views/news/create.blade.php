@extends('layouts.app')

@section('title', 'Новая новость — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Новости', 'url' => route('news.index')],
        ['label' => 'Новая новость', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="max-w-4xl space-y-6">
        <x-page-header title="Новая новость" />

        <x-ui.card>
            <form method="POST" action="{{ route('news.store') }}" class="space-y-5">
                @csrf

                <x-ui.form-group label="Тема новости" name="title" required>
                    <x-ui.input
                        type="text"
                        name="title"
                        id="title"
                        value="{{ old('title') }}"
                        maxlength="255"
                        :error="$errors->has('title')"
                    />
                </x-ui.form-group>

                <x-ui.form-group label="Описание новости" name="body" required>
                    <x-ui.textarea
                        name="body"
                        id="body"
                        rows="8"
                        :error="$errors->has('body')"
                    >{{ old('body') }}</x-ui.textarea>
                </x-ui.form-group>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" variant="primary">
                        Опубликовать
                    </x-ui.button>

                    <x-ui.button tag="a" :href="route('news.index')" variant="secondary">
                        Отменить
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection