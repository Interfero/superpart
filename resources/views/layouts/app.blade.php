<!DOCTYPE html>
<html lang="ru" class="{{ auth()->user()->theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SuperPart')</title>

    @include('layouts.favicon')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500,600,700"
        rel="stylesheet"
    />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/theme-overrides.css') }}?v=20260911-1">
    @stack('head')
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    @include('partials.navbar')

    <div class="max-w-full mx-auto px-4 mt-4 mb-2">
        @yield('breadcrumbs')
    </div>

    <main class="max-w-full mx-auto px-4 pb-8">
        @if (session('csrf_notice'))
            <div class="mb-4">
                <x-ui.alert type="error">
                    <p>{{ session('csrf_notice') }}</p>
                </x-ui.alert>
            </div>
        @endif
        @if (session('status'))
            <div class="mb-4">
                <x-ui.alert type="success">
                    {{ session('status') }}
                </x-ui.alert>
            </div>
        @endif
        @yield('content')
    </main>

    <script src="{{ asset('js/phone-mask.js') }}?v=1" defer></script>
    <script src="{{ asset('js/field-tips.js') }}?v=1" defer></script>
    @stack('scripts')

    {{-- Если в «Просмотр кода» нет superpart-layout — выгрузка не в тот каталог Laravel или не та копия сайта --}}
    <!-- superpart-layout:20260527 -->
</body>
</html>
