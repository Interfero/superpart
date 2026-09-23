@extends('layouts.app')

@section('title', 'Новости — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Новости', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
        @endif

        <div class="flex items-center justify-between gap-4">
            <x-page-header title="Новости" />

            @if (auth()->user()?->hasElevatedAccess())
                <x-ui.button tag="a" :href="route('news.create')" variant="primary">
                    Добавить новость
                </x-ui.button>
            @endif
        </div>

        <x-ui.card padding="none">
            <div class="divide-y divide-border">
                @forelse ($news as $item)
                    <a href="{{ route('news.show', $item->id) }}"
                       class="block px-4 py-4 hover:bg-muted/40">
                        <div class="text-xs text-muted-foreground">
                            {{ $item->created_at?->format('d.m.Y H:i') }}
                        </div>
                        <div class="mt-1 font-semibold text-foreground">
                            {{ $item->title }}
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Новостей пока нет.
                    </div>
                @endforelse
            </div>
        </x-ui.card>

        {{ $news->links() }}
    </div>
@endsection