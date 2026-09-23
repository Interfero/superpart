@extends('layouts.app')

@section('title', 'Обратная связь — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Обратная связь', 'url' => null]]" />
@endsection

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-foreground">Обратная связь</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Обращения, вопросы и скриншоты по работе кабинета.
                </p>
            </div>

            <a href="{{ route('feedback.create') }}"
               class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                Создать обращение
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-md border border-border bg-muted px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <x-ui.card padding="none">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/30">
                        <tr>
                            <th class="px-4 py-3 font-medium text-muted-foreground">ID</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Тема</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Пользователь</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Статус</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Скрин</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Создано</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr class="border-b border-border/60 hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <a href="{{ route('feedback.show', $ticket) }}"
                                       class="text-primary hover:underline">
                                        #{{ $ticket->id }}
                                    </a>
                                </td>

                                <td class="px-4 py-3">
                                    <a href="{{ route('feedback.show', $ticket) }}"
                                       class="font-medium text-foreground hover:text-primary">
                                        {{ $ticket->subject }}
                                    </a>

                                    <div class="mt-1 max-w-xl truncate text-xs text-muted-foreground">
                                        {{ $ticket->message }}
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    {{ $ticket->user->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $ticket->status_label }}
                                </td>

                                <td class="px-4 py-3">
                                    @if ($ticket->screenshot_path)
                                        <a href="{{ route('feedback.show', $ticket) }}"
                                           class="text-primary hover:underline">
                                            посмотреть
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    {{ $ticket->created_at ? $ticket->created_at->format('d.m.Y H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                    Обращений пока нет.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div>
            {{ $tickets->links() }}
        </div>
    </div>
@endsection