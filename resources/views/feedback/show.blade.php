@extends('layouts.app')

@section('title', 'Обращение #' . $ticket->id . ' — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Обратная связь', 'url' => route('feedback.index')],
        ['label' => 'Обращение #' . $ticket->id, 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-foreground">
                    Обращение #{{ $ticket->id }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Создано {{ $ticket->created_at ? $ticket->created_at->format('d.m.Y H:i') : '—' }}
                </p>
            </div>

            <x-ui.button tag="a" :href="route('feedback.index')" variant="secondary">
                Назад
            </x-ui.button>
        </div>

        <x-ui.card>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <div class="text-sm text-muted-foreground">Тема</div>
                    <div class="mt-1 font-semibold text-foreground">{{ $ticket->subject }}</div>
                </div>

                <div>
                    <div class="text-sm text-muted-foreground">Статус</div>
                    <div class="mt-1 font-semibold text-foreground">{{ $ticket->status_label }}</div>
                </div>

                <div>
                    <div class="text-sm text-muted-foreground">Пользователь</div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ $ticket->user->name ?? '—' }}
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="mb-3 text-lg font-semibold text-foreground">Описание</h2>
            <div class="whitespace-pre-line text-sm leading-7 text-muted-foreground">
                {{ $ticket->message }}
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="mb-3 text-lg font-semibold text-foreground">Вложения</h2>

            @php
                $attachments = is_array($ticket->attachments) ? $ticket->attachments : [];
            @endphp

            @if (count($attachments) > 0)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($attachments as $file)
                        @php
                            $path = is_array($file) ? ($file['path'] ?? null) : $file;
                            $name = is_array($file) ? ($file['name'] ?? basename((string) $path)) : basename((string) $file);
                            $size = is_array($file) ? ($file['size'] ?? null) : null;
                            $mime = is_array($file) ? ($file['mime'] ?? '') : '';
                            $url = $path
                                ? route('attachments.feedback', ['feedback' => $ticket, 'index' => $loop->index])
                                : null;
                        @endphp

                        @if ($url)
                            <a href="{{ $url }}"
                               target="_blank"
                               class="rounded-xl border border-border bg-muted/30 p-3 hover:bg-muted">
                                <div class="text-sm font-medium text-foreground break-all">
                                    {{ $name }}
                                </div>

                                <div class="mt-2 text-xs text-muted-foreground">
                                    @if ($size)
                                        {{ number_format($size / 1024, 0, ',', ' ') }} КБ
                                    @endif

                                    @if ($mime)
                                        <span>{{ $mime }}</span>
                                    @endif
                                </div>

                                <div class="mt-2 text-xs text-primary">
                                    Открыть файл
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="text-sm text-muted-foreground">
                    Вложения не приложены.
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection