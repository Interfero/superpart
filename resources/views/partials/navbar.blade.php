@php
    $navUser = auth()->user();

    $accNavItems = [
        ['label' => 'Список начислений', 'url' => route('transactions.index')],
    ];

    if ($navUser->can('use-withdrawals')) {
        $accNavItems[] = ['label' => 'Заявки на вывод д/с', 'url' => route('withdrawals.index')];
    }

    $reportNavItems = [
        ['label' => 'Отчёт по заявкам', 'url' => route('reports.orders')],
        ['label' => 'Отчёт по городам', 'url' => route('reports.cities')],
        ['label' => 'Отчёт по видам работ', 'url' => route('reports.work-types')],
        ['label' => 'Отчёт по отзывам', 'url' => route('reports.reviews')],
    ];

    $refNavItems = [
        ['label' => 'Источник', 'url' => route('sources.index')],
        ['label' => 'Виды работ', 'url' => route('work-types.index')],
    ];

    if ($navUser->can('access-cities-directory')) {
        array_splice($refNavItems, 1, 0, [
            ['label' => 'Актуальные города', 'url' => route('cities.index')],
        ]);
    }

    if ($navUser->can('access-employees-directory')) {
        array_unshift($refNavItems, [
            'label' => 'Список сотрудников',
            'url' => route('management.users.index'),
        ]);
    }

    $managementItems = [];

    if ($navUser->can('access-management-reference-sources')) {
        $managementItems[] = [
            'label' => 'Источники партнёров',
            'url' => route('management.reference-sources.index'),
        ];
    }

    if ($navUser->isDeveloper()) {
        $managementItems[] = [
            'label' => 'Синхронизация CRM',
            'url' => route('developer.sync'),
        ];
    }

    $navGroups = [];

    if ($navUser->isManager()) {
        $navGroups[] = [
            'title' => 'Отчёты',
            'items' => [
                ['label' => 'Отчёт по заявкам', 'url' => route('reports.orders')],
            ],
        ];

        $navGroups[] = [
            'title' => 'Справочники',
            'items' => [
                ['label' => 'Виды работ', 'url' => route('work-types.index')],
            ],
        ];
    } else {
        $navGroups[] = [
            'title' => 'Начисления',
            'items' => $accNavItems,
        ];

        $navGroups[] = [
            'title' => 'Отчёты',
            'items' => $reportNavItems,
        ];

        $navGroups[] = [
            'title' => 'Справочники',
            'items' => $refNavItems,
        ];

        if (! empty($managementItems)) {
            $navGroups[] = [
                'title' => 'Управление',
                'items' => $managementItems,
            ];
        }
    }

    $navGroups[] = [
        'title' => 'Новости',
        'items' => [
            ['label' => 'Список новостей', 'url' => route('news.index')],
        ],
    ];

    $navGroups[] = [
        'title' => 'Негативные отзывы',
        'items' => [
            ['label' => 'Список отзывов', 'url' => route('reviews.index')],
        ],
    ];
@endphp

<nav class="bg-card sticky top-0 z-50 shadow-lg border-b border-border">
    <div class="max-w-full mx-auto px-3 sm:px-4">
        <div class="flex items-center justify-between gap-2 h-12 min-w-0">

            <div class="flex items-center gap-1 min-w-0 flex-1">
                <a href="{{ route('home') }}"
                   class="text-primary font-bold text-base sm:mr-2 shrink-0 hover:text-primary/80 transition-colors">
                    {{ $navUser->isManager() ? 'Партнёр' : 'Главная' }}
                </a>

                <div class="hidden lg:flex items-center gap-0 min-w-0">
                    @foreach ($navGroups as $group)
                        <x-dropdown-menu
                            :title="$group['title']"
                            :items="$group['items']"
                        />
                    @endforeach

                    <div class="theme-switcher ml-1" aria-label="Цветовая тема">
                        @foreach (['dark' => 'Тёмная', 'light' => 'Светлая'] as $themeKey => $themeLabel)
                            <form method="POST" action="{{ route('settings.theme') }}">
                                @csrf
                                <input type="hidden" name="theme" value="{{ $themeKey }}">
                                <button type="submit" data-theme-choice="{{ $themeKey }}" aria-pressed="{{ $navUser->theme === $themeKey ? 'true' : 'false' }}">
                                    {{ $themeLabel }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                @if ($navUser->showsWalletInNavbar())
                    <div class="max-w-[9rem] sm:max-w-none min-w-0 [&_a]:text-xs sm:[&_a]:text-sm [&_a]:px-2 [&_a]:py-1 sm:[&_a]:px-3 sm:[&_a]:py-1.5 [&_.inline-flex]:text-xs sm:[&_.inline-flex]:text-sm [&_.inline-flex]:px-2 [&_.inline-flex]:py-1 sm:[&_.inline-flex]:px-3 sm:[&_.inline-flex]:py-1.5">
                        <x-balance-badge
                            :amount="$navUser->walletBalance()"
                            :href="$navUser->can('use-withdrawals') ? route('withdrawals.create') : route('transactions.index')"
                        />
                    </div>
                @endif

                <x-ui.button
                    tag="a"
                    :href="route('orders.create')"
                    variant="primary"
                    size="md"
                    class="px-2.5 py-1.5 sm:px-4 text-xs sm:text-sm whitespace-nowrap">
                    Создать
                </x-ui.button>

                <div class="dropdown-menu relative hidden lg:block" data-dropdown>
                    <button type="button"
                            class="flex items-center gap-1 px-2 sm:px-3 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors max-w-[10rem] sm:max-w-none truncate"
                            data-dropdown-toggle>

                        <span class="truncate">
                            {{ auth()->user()->displayFullName() }}, ID {{ auth()->user()->id }}
                        </span>

                        <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200"
                             data-dropdown-arrow
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">

                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div class="dropdown-panel absolute right-0 top-full mt-0 min-w-[240px] bg-card rounded-md shadow-xl border border-border opacity-0 invisible transition-all duration-200 z-50"
                         data-dropdown-panel>

                        <a href="{{ route('settings.index') }}"
                           class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors rounded-t-md">
                            Настройки
                        </a>

                        <a href="{{ route('notifications.index') }}"
                           class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                            Уведомления
                        </a>

                        <a href="{{ route('feedback.index') }}"
                           class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                            Обратная связь
                        </a>

                        <div class="border-t border-border my-1"></div>

                        <a href="{{ route('orders.create', ['server_type' => 'computer_help']) }}"
                           class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                            Компьютерная помощь
                        </a>

                        <a href="{{ route('orders.create', ['server_type' => 'appliance_repair']) }}"
                           class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                            Ремонт бытовой техники
                        </a>

                        <a href="{{ route('orders.create', ['server_type' => 'handyman']) }}"
                           class="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                            Мастер на час
                        </a>

                        <div class="border-t border-border my-1"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button type="submit"
                                    class="block w-full text-left px-4 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors rounded-b-md">
                                Выход
                            </button>
                        </form>
                    </div>
                </div>

                <button type="button"
                        class="lg:hidden inline-flex items-center justify-center rounded-md border border-border bg-card p-2 text-foreground hover:bg-muted transition-colors"
                        data-mobile-nav-toggle
                        aria-expanded="false"
                        aria-controls="mobile-nav-panel"
                        aria-label="Открыть меню">

                    <svg class="h-5 w-5"
                         data-mobile-nav-icon-open
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>

                    <svg class="h-5 w-5 hidden"
                         data-mobile-nav-icon-close
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</nav>

<div class="fixed inset-0 z-[60] hidden lg:hidden"
     data-mobile-nav-root
     id="mobile-nav-root"
     aria-hidden="true">

    <div class="absolute inset-0 bg-background/70 backdrop-blur-[2px]"
         data-mobile-nav-backdrop
         aria-hidden="true"></div>

    <aside id="mobile-nav-panel"
           class="absolute left-0 top-0 flex h-full w-[min(100vw,20rem)] flex-col border-r border-border bg-card shadow-xl"
           role="dialog"
           aria-modal="true"
           aria-label="Навигация">

        <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <span class="font-semibold text-foreground">Меню</span>

            <button type="button"
                    class="rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                    data-mobile-nav-close
                    aria-label="Закрыть меню">

                <svg class="h-5 w-5"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">

                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-2 py-3" data-mobile-nav-scroll>
            @foreach ($navGroups as $group)
                <div class="mb-4 last:mb-0">
                    <div class="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ $group['title'] }}
                    </div>

                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}"
                                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="mx-2 mb-4 border-t border-border pt-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Цветовая тема</div>
                <div class="theme-switcher">
                    @foreach (['dark' => 'Тёмная', 'light' => 'Светлая'] as $themeKey => $themeLabel)
                        <form method="POST" action="{{ route('settings.theme') }}">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $themeKey }}">
                            <button type="submit" data-theme-choice="{{ $themeKey }}" aria-pressed="{{ $navUser->theme === $themeKey ? 'true' : 'false' }}">{{ $themeLabel }}</button>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 border-t border-border pt-4">
                <div class="px-2 pb-2 text-xs text-muted-foreground truncate">
                    {{ auth()->user()->name }}, ID {{ auth()->user()->id }}
                </div>

                <a href="{{ route('settings.index') }}"
                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                    Настройки
                </a>

                <a href="{{ route('notifications.index') }}"
                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                    Уведомления
                </a>

                <a href="{{ route('feedback.index') }}"
                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                    Обратная связь
                </a>

                <div class="my-2 border-t border-border"></div>

                <a href="{{ route('orders.create', ['server_type' => 'computer_help']) }}"
                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                    Компьютерная помощь
                </a>

                <a href="{{ route('orders.create', ['server_type' => 'appliance_repair']) }}"
                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                    Ремонт бытовой техники
                </a>

                <a href="{{ route('orders.create', ['server_type' => 'handyman']) }}"
                   class="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted">
                    Мастер на час
                </a>

                <div class="my-2 border-t border-border"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit"
                            class="block w-full rounded-md px-3 py-2 text-left text-sm text-foreground hover:bg-muted">
                        Выход
                    </button>
                </form>
            </div>
</nav>

@once
    @push('scripts')
        <script>
            (() => {
                const applyTheme = (activeTheme) => {
                    document.documentElement.classList.toggle('dark', activeTheme === 'dark');
                    document.documentElement.classList.toggle('light', activeTheme === 'light');

                    try {
                        localStorage.setItem('theme', activeTheme);
                    } catch (_) {
                        // В приватном режиме тема всё равно применяется в текущей вкладке.
                    }

                    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
                        button.setAttribute('aria-pressed', button.dataset.themeChoice === activeTheme ? 'true' : 'false');
                    });
                };

                const persistTheme = (activeTheme) => {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    if (!token) {
                        return;
                    }

                    fetch('{{ route('settings.theme') }}', {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ theme: activeTheme }),
                    }).catch(() => {});
                };

                document.addEventListener('DOMContentLoaded', () => {
                    applyTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                });
                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-theme-choice]');

                    if (!button) {
                        return;
                    }

                    const nextTheme = button.dataset.themeChoice;

                    if (!['dark', 'light'].includes(nextTheme)) {
                        return;
                    }

                    event.preventDefault();
                    applyTheme(nextTheme);
                    persistTheme(nextTheme);
                });
            })();
        </script>
    @endpush
@endonce
    </aside>
</div>
