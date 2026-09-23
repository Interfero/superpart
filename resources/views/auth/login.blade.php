<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Вход — SuperPart</title>

    @include('layouts.favicon')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500,600,700"
        rel="stylesheet"
    />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">
    <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-10 px-6 py-10 sm:px-10 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-1 items-center justify-center lg:justify-start">
            <h1
                class="font-bold tracking-tight text-foreground"
                style="font-size: clamp(3.5rem, 9vw, 7.5rem); line-height: 1.05;"
            >
                Super<span class="text-primary">Part</span>
            </h1>
        </div>

        <div class="flex flex-1 items-center justify-center lg:justify-end">
            <div class="w-full max-w-md">
                <x-ui.card padding="lg" :shadow="true">
                    <h2 class="mb-6 text-center text-2xl font-bold text-foreground">
                        Вход в личный кабинет
                    </h2>

                    @if (session('csrf_notice'))
                        <x-ui.alert type="error" class="mb-4">
                            <p>{{ session('csrf_notice') }}</p>
                        </x-ui.alert>
                    @endif

                    @if ($errors->any())
                        <x-ui.alert type="error">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </x-ui.alert>
                    @endif

                    <form method="POST" action="{{ route('login') }}" id="login-form" autocomplete="on" class="space-y-4">
                        @csrf

                        <div>
                            <x-ui.label for="email">E-mail</x-ui.label>
                            <x-ui.input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="email@example.com"
                                :error="$errors->has('email')"
                            />
                        </div>

                        <div>
                            <x-ui.label for="password">Пароль</x-ui.label>
                            <div class="relative">
                                <x-ui.input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Введите пароль"
                                    class="pr-11"
                                    :error="$errors->has('password')"
                                />
                                <button
                                    type="button"
                                    id="password-toggle"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
                                    aria-label="Показать пароль"
                                    title="Показать пароль"
                                >
                                    <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg id="icon-eye-off" class="hidden" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.585 10.585A2 2 0 0012 14a2 2 0 001.414-.586M9.88 4.24A9.96 9.96 0 0112 4c4.478 0 8.268 2.943 9.542 7a10.05 10.05 0 01-2.122 3.292M6.228 6.228A10.02 10.02 0 002.458 12c1.274 4.057 5.065 7 9.542 7 1.35 0 2.635-.265 3.81-.748" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <x-ui.button type="submit" variant="primary" size="lg" class="mt-2 w-full font-semibold">
                            Войти
                        </x-ui.button>

                        <x-ui.button
                            tag="a"
                            :href="route('offer')"
                            variant="outline"
                            size="lg"
                            class="w-full font-semibold"
                        >
                            Договор оферты
                        </x-ui.button>
                    </form>
                </x-ui.card>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const password = document.getElementById('password');
            const toggle = document.getElementById('password-toggle');
            const iconEye = document.getElementById('icon-eye');
            const iconEyeOff = document.getElementById('icon-eye-off');
            // Снести устаревшие credentials из localStorage (ТЗ FR-PRIV-02).
            try {
                localStorage.removeItem('superpart_remember_credentials');
            } catch (e) {}

            toggle?.addEventListener('click', function () {
                const show = password.type === 'password';
                password.type = show ? 'text' : 'password';
                iconEye?.classList.toggle('hidden', show);
                iconEyeOff?.classList.toggle('hidden', !show);
                toggle.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
                toggle.setAttribute('title', show ? 'Скрыть пароль' : 'Показать пароль');
            });
        })();
    </script>
</body>
</html>
