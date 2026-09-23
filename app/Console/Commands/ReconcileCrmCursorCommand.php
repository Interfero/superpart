<?php

namespace App\Console\Commands;

use App\Services\LevelionApiService;
use App\Services\OrderSnapshotApplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Курсорная сверка CRM→SP (ТЗ FR-REC-01/02) без N+1 в UI.
 */
class ReconcileCrmCursorCommand extends Command
{
    protected $signature = 'orders:reconcile-cursor
                            {--limit=80 : Размер страницы}
                            {--reset : Сбросить курсор на 0}';

    protected $description = 'Забрать изменения CRM по курсору outbox и применить snapshot';

    private const CURSOR_KEY = 'superpart_crm_changes_cursor';

    public function handle(LevelionApiService $api, OrderSnapshotApplier $applier): int
    {
        if (! $api->isConfigured()) {
            $this->warn('CRM не настроена');

            return self::SUCCESS;
        }

        if ($this->option('reset')) {
            Cache::forever(self::CURSOR_KEY, 0);
            $this->info('cursor reset to 0');
        }

        $cursor = (int) Cache::get(self::CURSOR_KEY, 0);
        $limit = max(1, (int) $this->option('limit'));
        $data = $api->fetchSuperpartOrderChanges($cursor, $limit);
        if ($data === null) {
            $this->warn('Не удалось получить changes');
            Log::warning('orders:reconcile-cursor: CRM changes unavailable, skip');

            return self::SUCCESS;
        }

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $applied = 0;
        $dup = 0;
        $failed = 0;

        foreach ($items as $item) {
            $snapshot = $item['snapshot'] ?? null;
            if (! is_array($snapshot)) {
                $failed++;
                continue;
            }
            try {
                $result = $applier->apply($snapshot);
                if (! empty($result['duplicate']) || ! empty($result['stale'])) {
                    $dup++;
                } elseif ($result['ok'] ?? false) {
                    $applied++;
                } else {
                    $failed++;
                    Log::warning('orders:reconcile-cursor apply rejected', [
                        'event_id' => $snapshot['event_id'] ?? null,
                        'message' => $result['message'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('orders:reconcile-cursor apply error', [
                    'error' => $e->getMessage(),
                    'event_id' => $item['event_id'] ?? null,
                ]);
            }
        }

        $next = (int) ($data['next_cursor'] ?? $cursor);
        // Двигаем курсор всегда: failed элементы уже в inbox rejected; иначе залипнем.
        Cache::forever(self::CURSOR_KEY, $next);

        if (Schema::hasTable('reconcile_runs')) {
            DB::table('reconcile_runs')->insert([
                'kind' => 'cursor',
                'dry_run' => false,
                'checked' => count($items),
                'equal_count' => $dup,
                'repaired' => $applied,
                'excluded' => 0,
                'financial_discrepancy' => 0,
                'failed' => $failed,
                'status' => $failed > 0 ? 'partial' : 'ok',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $this->line("cursor={$cursor}→{$next} items=".count($items).' applied='.$applied.' dup_or_stale='.$dup.' failed='.$failed);

        return self::SUCCESS;
    }
}
