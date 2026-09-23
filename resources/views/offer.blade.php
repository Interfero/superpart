<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Договор публичной оферты — SuperPart</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
        <header class="mb-8 flex items-center justify-between gap-4">
            <div>
                <a href="/" class="text-xl font-bold hover:opacity-80">SuperPart</a>
                <div class="text-sm text-muted-foreground">Договор публичной оферты</div>
            </div>

            <a href="/" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">
                Назад
            </a>
        </header>

        <main class="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Договор публичной оферты</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Редакция от {{ now()->format('d.m.Y') }}
                </p>
            </div>

            <div class="space-y-6 text-sm leading-7 text-muted-foreground">
                <section>
                    <h2 class="mb-2 text-lg font-semibold text-foreground">1. Общие положения</h2>
                    <p>
                        Настоящий документ является предварительным шаблоном договора публичной оферты.
                        Финальный текст должен быть подготовлен и проверен юристом перед публикацией.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-foreground">2. Предмет договора</h2>
                    <p>
                        Партнёр передаёт заявки через личный кабинет или согласованные каналы связи.
                        Сервис принимает, обрабатывает и фиксирует статусы заявок в информационной системе.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-foreground">3. Условия начислений</h2>
                    <p>
                        Начисления партнёру рассчитываются на основании подтверждённых заказов,
                        статусов обработки и индивидуальных условий сотрудничества.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-foreground">4. Права и обязанности сторон</h2>
                    <p>
                        Партнёр обязуется передавать корректные данные по заявкам. Сервис обязуется
                        отображать доступную информацию о статусах, начислениях и истории обработки заявок.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-foreground">5. Обратная связь</h2>
                    <p>
                        Партнёр может направлять обращения через личный кабинет. При необходимости к обращению
                        могут быть приложены скриншоты и дополнительные материалы.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-foreground">6. Заключительные положения</h2>
                    <p>
                        Использование личного кабинета означает согласие с условиями настоящей оферты,
                        если иное не предусмотрено отдельным соглашением сторон.
                    </p>
                </section>
            </div>

            <div class="mt-8 rounded-xl bg-muted p-4 text-sm text-muted-foreground">
                Внимание: это временный текст. Его нужно заменить на финальную юридическую редакцию.
            </div>
        </main>
    </div>
</body>
</html>