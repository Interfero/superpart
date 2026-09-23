@extends('layouts.app')

@section('title', $newsItem->title . ' — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Новости', 'url' => route('news.index')],
        ['label' => $newsItem->title, 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="max-w-4xl space-y-6">
        <x-ui.card>
            <div class="text-sm text-muted-foreground">
                {{ $newsItem->created_at?->format('d.m.Y H:i') }}
            </div>

            <h1 class="mt-2 text-2xl font-bold text-foreground">
                {{ $newsItem->title }}
            </h1>

            <div class="mt-6 whitespace-pre-line text-sm leading-7 text-muted-foreground">
                {{ $newsItem->body }}
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button tag="a" :href="route('news.index')" variant="secondary">
                Назад
            </x-ui.button>

            @if (auth()->user()?->hasElevatedAccess())
                <form method="POST" action="{{ route('news.destroy', $newsItem->id) }}" onsubmit="return confirm('Удалить эту новость?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="destructive">
                        Удалить
                    </x-ui.button>
                </form>
            @endif
        </div>
    </div>
@endsection