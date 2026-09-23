<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CPU_THROTTLE_20260920: курсор реже — на shared REG минутный прогон + stacktrace при недоступной CRM сжёг CPU.
Schedule::command('orders:reconcile-cursor --limit=80')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->onOneServer();
Schedule::command('orders:reconcile-full --limit=80')
    ->dailyAt('03:40')
    ->withoutOverlapping(120)
    ->onOneServer();
Schedule::command('sync:health-check')
    ->everyFifteenMinutes()
    ->withoutOverlapping(3)
    ->onOneServer();

// Legacy N+1 GET /partner-orders/{id} забивал throttle и глушил создание источников.
// Курсор + ночная полная сверка закрывают тот же контур.
Schedule::command('orders:reconcile-crm --limit=20')
    ->hourly()
    ->withoutOverlapping(4)
    ->onOneServer();

Schedule::command('sources:retry-crm-push --limit=20')
    ->everyFifteenMinutes()
    ->withoutOverlapping(5)
    ->onOneServer();

// Каждый час: closed_local у начисленных заявок (холд 36ч / «доступно к выводу»).
Schedule::command('orders:backfill-closed-local --limit=500')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();

// SLA ~30 мин на смену источника; чаще на shared только жгло CPU.
Schedule::command('orders:sync-sources-from-crm --limit=200')
    ->everyThirtyMinutes()
    ->withoutOverlapping(8)
    ->onOneServer();
