<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SuperPart</title>
    <meta http-equiv="refresh" content="0;url={{ auth()->check() ? url('/dashboard') : route('login') }}">
</head>
<body>
    {{--
        Временный входной экран — форма логина в стиле Lead Control:
        resources/views/auth/login.blade.php (+ кнопка «Договор оферты»).

        Полная маркетинговая страница сохранена в:
        resources/views/welcome_full_archive.blade.php
    --}}
    <p>
        <a href="{{ auth()->check() ? url('/dashboard') : route('login') }}">
            Перейти {{ auth()->check() ? 'в кабинет' : 'ко входу' }}
        </a>
    </p>
</body>
</html>
