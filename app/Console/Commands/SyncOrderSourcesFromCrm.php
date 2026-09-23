<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\LevelionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Сверка РК (источника) заявок с CRM — чинит дрейф вроде «СММ» vs «Листовка_1».
 */
class SyncOrderSourcesFromCrm extends Command
{
    protected $signature = 'orders:sync-sources-from-crm
                            {--limit=200 : Максимум заказов за запуск}
                            {--order= : Только один order id}
                            {--dry-run : Только отчёт}';

    protected $description = 'Сверить reference_source_id заявок с source_id в CRM';

    public function handle(LevelionApiService $api): int
    {
        if (! $api->isConfigured()) {
            $this->warn('CRM не настроена');

            return self::SUCCESS;
        }

        if (Cache::has('levelion_crm_down')) {
            $this->warn('CRM временно недоступна (circuit breaker)');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $only = $this->option('order');

        $query = Order::query()->orderByDesc('id');
        if ($only !== null && $only !== '') {
            $query->whereKey((int) $only);
        } else {
            $query->limit(max(1, (int) $this->option('limit')));
        }

        $orders = $query->get();
        $checked = 0;
        $fixed = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $crmId = $order->crmOrderId();
            if (! $crmId) {
                $skipped++;
                continue;
            }

            $data = $api->fetchPartnerOrderFromCrm((int) $crmId, 5);
            if ($data === null) {
                $skipped++;
                continue;
            }

            $checked++;
            $before = (int) ($order->reference_source_id ?? 0);
            $crmSrc = (int) ($data['source_id'] ?? 0);
            $crmName = (string) ($data['marketing_source_name'] ?? '');

            if ($crmSrc < 1) {
                continue;
            }

            if ($dry) {
                $spLvl = (int) ($order->referenceSource?->levelion_source_id
                    ?? \App\Models\ReferenceSource::query()->whereKey($before)->value('levelion_source_id')
                    ?? 0);
                if ($spLvl !== $crmSrc) {
                    $this->warn("dry #{$order->id} SP_lvl={$spLvl} CRM={$crmSrc}/{$crmName}");
                }

                continue;
            }

            if ($api->syncOrderSourceFromCrmPayload($order, $data) || $api->syncOrderBodyFromCrmPayload($order, $data)) {
                $order->refresh();
                $fixed++;
                $this->info("FIXED #{$order->id} => {$order->sourceDisplayName()} / {$order->client_name}");
                Log::info('orders:sync-sources-from-crm fixed', [
                    'order_id' => $order->id,
                    'before_ref' => $before,
                    'after_ref' => $order->reference_source_id,
                    'crm_source_id' => $crmSrc,
                ]);
            }

            usleep(40000);
        }

        $this->line("checked={$checked} fixed={$fixed} skipped={$skipped} dry=".($dry ? '1' : '0'));

        return self::SUCCESS;
    }
}
