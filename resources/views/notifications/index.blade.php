@extends('layouts.app')


@section('title', 'Уведомления — SuperPart')


@section('breadcrumbs')

    <x-breadcrumbs :items="[

        ['label' => 'Главная', 'url' => route('home')],

        ['label' => 'Уведомления', 'url' => null],

    ]" />

@endsection


@section('content')

    <div class="space-y-4">

        <div class="flex flex-wrap items-center justify-between gap-3">

            <div>

                <h1 class="text-2xl font-bold text-foreground">Уведомления</h1>

                <p class="mt-1 text-sm text-muted-foreground">

                    Системные напоминания и важные события.

                </p>

            </div>


            <form method="POST" action="{{ route('notifications.read-all') }}">

                @csrf

                <x-ui.button type="submit" variant="outline" size="md">

                    Прочитать все

                </x-ui.button>

            </form>

        </div>


        @if (session('success'))

            <x-ui.alert type="success">

                {{ session('success') }}

            </x-ui.alert>

        @endif


        <x-ui.card padding="none">

            <div class="divide-y divide-border">

                @forelse ($notifications as $notification)

                    <div class="px-4 py-4 {{ $notification->read_at ? '' : 'bg-primary/5' }}">

                        <div class="flex flex-wrap items-start justify-between gap-3">

                            <div>

                                <div class="flex flex-wrap items-center gap-2">

                                    <h2 class="font-semibold text-foreground">

                                        {{ $notification->title }}

                                    </h2>


                                    @if (! $notification->read_at)

                                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">

                                            новое

                                        </span>

                                    @endif

                                </div>


                                @if ($notification->message)

                                    <p class="mt-1 text-sm text-muted-foreground">

                                        {{ $notification->message }}

                                    </p>

                                @endif


                                <div class="mt-2 text-xs text-muted-foreground">

                                    {{ $notification->created_at ? $notification->created_at->format('d.m.Y H:i') : '—' }}

                                </div>

                            </div>


                            <div class="flex flex-wrap gap-2">

                                @if ($notification->url)

                                    <x-ui.button tag="a" :href="$notification->url" variant="secondary" size="sm">

                                        Открыть

                                    </x-ui.button>

                                @endif


                                @if (! $notification->read_at)

                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">

                                        @csrf

                                        <x-ui.button type="submit" variant="outline" size="sm">

                                            Прочитано

                                        </x-ui.button>

                                    </form>

                                @endif

                            </div>

                        </div>

                    </div>

                @empty

                    <div class="px-4 py-10 text-center text-muted-foreground">

                        Уведомлений пока нет.

                    </div>

                @endforelse

            </div>

        </x-ui.card>


        @if ($notifications->hasPages())

            <div>

                {{ $notifications->links() }}

            </div>

        @endif

    </div>

@endsection
