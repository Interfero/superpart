<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг sync inbox/outbox-метрик на стороне SP (ТЗ §13).
 */
class SyncHealthCheckCommand extends Command
{
    protected $signature = 'sync:health-check {--json : JSON-вывод}';

    protected $description = 'Проверка inbox/dead-letter и возраста необработанных sync-событий';

    public function handle(): int
    {
        $checks = [];
        $ok = true;

        if (Schema::hasTable('sync_inbox')) {
            $pending = (int) DB::table('sync_inbox')->whereIn('result', ['received', 'pending'])->count();
            $rejected = (int) DB::table('sync_inbox')->where('result', 'rejected')->count();
            $oldest = DB::table('sync_inbox')
                ->whereIn('result', ['received', 'pending'])
                ->orderBy('received_at')
                ->value('received_at');
            $age = $oldest ? now()->diffInSeconds(\Carbon\Carbon::parse($oldest)) : 0;
            $inboxOk = $rejected < 20 && ($pending === 0 || $age < 300);
            if (! $inboxOk) {
                $ok = false;
            }
            $checks['sync_inbox'] = [
                'ok' => $inboxOk,
                'pending' => $pending,
                'rejected' => $rejected,
                'oldest_pending_age_sec' => $age,
            ];
        } else {
            $checks['sync_inbox'] = ['ok' => true, 'skipped' => true];
        }

        $full = Cache::get('superpart_full_reconcile_last');
        $checks['full_reconcile'] = [
            'ok' => true,
            'last' => $full,
        ];

        $cursor = Cache::get('superpart_crm_changes_cursor');
        $checks['cursor'] = [
            'ok' => true,
            'value' => $cursor,
        ];

        if (Schema::hasTable('sync_dead_letter')) {
            $dead = (int) DB::table('sync_dead_letter')->count();
            $checks['dead_letter'] = [
                'ok' => $dead === 0,
                'count' => $dead,
            ];
            if ($dead > 0) {
                $ok = false;
                Log::warning('sync:health-check dead-letter not empty', ['count' => $dead]);
            }
        }

        if (Schema::hasTable('financial_discrepancies')) {
            $open = (int) DB::table('financial_discrepancies')->where('status', 'open')->count();
            $checks['financial_discrepancies'] = [
                'ok' => $open === 0,
                'open' => $open,
            ];
            if ($open > 0) {
                $ok = false;
            }
        }

        $snapshot = [
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];

        Cache::forever('superpart_sync_health_last', $snapshot);

        if (! $ok) {
            Log::warning('sync:health-check degraded', $snapshot);
        }

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('status='.$snapshot['status']);
            foreach ($checks as $name => $check) {
                $this->line($name.': '.json_encode($check, JSON_UNESCAPED_UNICODE));
            }
        }

        // degraded пишем в кэш/лог; FAILURE ронял schedule:run и заливал laravel.log stacktrace.
        return self::SUCCESS;
    }
}
