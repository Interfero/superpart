<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\LevelionApiService;
use App\Services\OrderSnapshotApplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Суточная полная сверка CRM↔SP (ТЗ FR-REC-03).
 * Результат: checked / equal / repaired / excluded / financial_discrepancy / failed.
 */
class ReconcileCrmFullCommand extends Command
{
    protected $signature = 'orders:reconcile-full
                            {--limit=50 : Размер страницы}
                            {--max-pages=500 : Защита от бесконечного цикла}
                            {--dry-run : Только сравнить, не применять}';

    protected $description = 'Полная постраничная сверка всех SP-релевантных заявок с CRM';

    public function handle(LevelionApiService $api, OrderSnapshotApplier $applier): int
    {
        if (! $api->isConfigured()) {
            $this->warn('CRM не настроена');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'checked' => 0,
            'equal' => 0,
            'repaired' => 0,
            'excluded' => 0,
            'financial_discrepancy' => 0,
            'failed' => 0,
        ];

        $startedAt = now();
        $afterId = 0;
        for ($page = 0; $page < $maxPages; $page++) {
            $data = $api->fetchSuperpartOrdersFull($afterId, $limit);
            if ($data === null) {
                $this->error("Не удалось получить full page after_id={$afterId}");
                $stats['failed']++;
                break;
            }

            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $stats['checked']++;
                $orderId = (int) ($item['order_id'] ?? 0);
                $crmVersion = (int) ($item['sync_version'] ?? 0);
                $snapshot = is_array($item['snapshot'] ?? null) ? $item['snapshot'] : null;
                if ($orderId < 1 || $snapshot === null) {
                    $stats['failed']++;
                    continue;
                }

                $crmChecksum = (string) ($snapshot['checksum'] ?? '');
                $orderBlock = is_array($snapshot['order'] ?? null) ? $snapshot['order'] : [];
                $spAvailable = (bool) ($orderBlock['source_available_for_superpart'] ?? false);

                /** @var Order|null $local */
                $local = Order::query()->whereCrmRecord($orderId)->first();

                if (! $spAvailable) {
                    $stats['excluded']++;
                    if ($local && ! $dryRun) {
                        try {
                            // leave-SP сценарий через тот же applier
                            $applier->apply($this->normalizePayload($snapshot, $local));
                        } catch (\Throwable $e) {
                            $stats['failed']++;
                            Log::warning('orders:reconcile-full exclude apply failed', [
                                'order_id' => $orderId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    continue;
                }

                $localVersion = (int) ($local?->crm_sync_version ?? 0);
                $localChecksum = (string) ($local?->crm_checksum ?? '');

                if ($local && $localVersion === $crmVersion && $localChecksum !== '' && hash_equals($localChecksum, $crmChecksum)) {
                    $stats['equal']++;
                    $stats['financial_discrepancy'] += $this->financeMismatch($local, $orderBlock) ? 1 : 0;
                    continue;
                }

                if ($dryRun) {
                    $stats['repaired']++; // would repair
                    continue;
                }

                try {
                    $payload = $this->normalizePayload($snapshot, $local);
                    // Если версия не выросла, но checksum другой — форсируем bump для применения истины CRM.
                    if ($local && $localVersion >= $crmVersion && ! hash_equals($localChecksum, $crmChecksum)) {
                        $payload['event_version'] = $localVersion + 1;
                        if (isset($payload['order']) && is_array($payload['order'])) {
                            // checksum пересчитает applier? нет — он сверяет присланный. Пересоберём checksum локально нельзя без CRM.
                            // Оставляем событие как есть с bumped version — applier сверит checksum с order block + bumped version → mismatch.
                            // Поэтому для force repair вызываем внутренний путь: подставим event_version в checksum fields via recompute on SP after bump.
                        }
                        $payload = $this->bumpVersionKeepingOrderTruth($payload, $localVersion + 1);
                    }

                    $result = $applier->apply($payload);
                    if ($result['ok'] ?? false) {
                        if (! empty($result['stale']) || ! empty($result['duplicate'])) {
                            $stats['equal']++;
                        } else {
                            $stats['repaired']++;
                        }
                    } else {
                        $stats['failed']++;
                        Log::warning('orders:reconcile-full apply rejected', [
                            'order_id' => $orderId,
                            'message' => $result['message'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::warning('orders:reconcile-full apply error', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $afterId = (int) ($data['next_after_id'] ?? $afterId);
            if (empty($data['has_more'])) {
                break;
            }
        }

        $finished = array_merge($stats, [
            'finished_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
        ]);
        Cache::forever('superpart_full_reconcile_last', $finished);

        if (Schema::hasTable('reconcile_runs')) {
            DB::table('reconcile_runs')->insert([
                'kind' => 'full',
                'dry_run' => $dryRun,
                'checked' => $stats['checked'],
                'equal_count' => $stats['equal'],
                'repaired' => $stats['repaired'],
                'excluded' => $stats['excluded'],
                'financial_discrepancy' => $stats['financial_discrepancy'],
                'failed' => $stats['failed'],
                'status' => $stats['failed'] > 0 ? 'failed' : 'ok',
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        }

        $this->table([array_keys($stats)], [array_values($stats)]);
        $this->info($dryRun ? 'dry-run finished' : 'full reconcile finished');

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalizePayload(array $snapshot, ?Order $local): array
    {
        return [
            'event_id' => (string) ($snapshot['event_id'] ?? ''),
            'event_type' => (string) ($snapshot['event_type'] ?? 'order.snapshot.changed'),
            'event_version' => (int) ($snapshot['event_version'] ?? 0),
            'occurred_at' => $snapshot['occurred_at'] ?? null,
            'order' => $snapshot['order'] ?? [],
            'checksum' => (string) ($snapshot['checksum'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function bumpVersionKeepingOrderTruth(array $payload, int $newVersion): array
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $payload['event_version'] = $newVersion;
        $payload['checksum'] = \App\Support\OrderSnapshotChecksum::compute($order, $newVersion);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $orderBlock
     */
    private function financeMismatch(Order $local, array $orderBlock): bool
    {
        $expected = (int) ($orderBlock['reward_amount'] ?? 0);
        if ($expected <= 0) {
            return false;
        }
        $charged = (float) Transaction::query()
            ->where('order_id', $local->id)
            ->where('operation_type', 'charge')
            ->sum('amount');
        $corrections = (float) Transaction::query()
            ->where('order_id', $local->id)
            ->where('operation_type', 'correction')
            ->sum('amount');
        $effective = (int) round($charged + $corrections);

        return abs($effective - $expected) > 0;
    }
}
