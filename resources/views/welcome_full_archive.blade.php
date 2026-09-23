<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SuperPart</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">
    {{--
        Полная маркетинговая версия входной страницы сохранена в:
        resources/views/welcome_full_archive.blade.php
        (вернуть: скопировать содержимое обратно в welcome.blade.php)
    --}}

    <div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">SuperPart</h1>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            @auth
                <a href="{{ url('/dashboard') }}" class="rounded-md bg-primary px-5 py-3 text-sm font-medium text-primary-foreground">
                    Войти в кабинет
                </a>
            @else
                <a href="{{ route('login') }}" class="rounded-md bg-primary px-5 py-3 text-sm font-medium text-primary-foreground">
                    Войти в кабинет
                </a>
            @endauth

            <a href="{{ route('offer') }}" class="rounded-md border border-border px-5 py-3 text-sm font-medium hover:bg-muted">
                Договор оферты
            </a>
        </div>
    </div>
</body>
</html>
