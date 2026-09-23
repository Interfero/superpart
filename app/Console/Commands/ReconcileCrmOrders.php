<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\LevelionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Сверка статусов/начислений с CRM: ловит «Отказ» при completed+сумме и пропущенные проведения.
 */
class ReconcileCrmOrders extends Command
{
    protected $signature = 'orders:reconcile-crm
                            {--limit=80 : Максимум заказов за запуск}
                            {--dry-run : Только отчёт, без записи}';

    protected $description = 'Сверить открытые и «отказ»-заявки с CRM и доначислить пропущенные проведения';

    public function handle(LevelionApiService $api): int
    {
        if (! $api->isConfigured()) {
            $this->warn('CRM не настроена');

            return self::SUCCESS;
        }

        if (Cache::has('levelion_crm_down')) {
            $this->warn('CRM временно помечена как недоступная (circuit breaker)');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        $candidates = Order::query()
            ->where(function ($q) {
                $q->whereNotNull('levelion_order_id')
                    ->orWhereRaw('id > 0');
            })
            ->where(function ($q) {
                $q->whereIn('status', [
                    'refusal',
                    'refusal_non_profile',
                    'not_processed',
                    'waiting',
                    'clarification',
                    'in_work',
                    'in_work_sd',
                    'on_way',
                    'ready',
                ])->orWhere(function ($q2) {
                    $q2->where('status', 'waiting_payment')
                        ->where(function ($q3) {
                            $q3->whereNull('charge_amount')->orWhere('charge_amount', '<=', 0);
                        });
                });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $changed = 0;
        $checked = 0;

        foreach ($candidates as $order) {
            if (! $order->crmOrderId()) {
                continue;
            }

            $checked++;
            Cache::forget('crm_order_status_sync:'.$order->id);

            if ($dry) {
                $data = $api->fetchPartnerOrderFromCrm((int) $order->crmOrderId(), 5);
                if ($data && (($data['order_status'] ?? '') === 'completed')) {
                    $this->line("dry #{$order->id} local={$order->status} crm=completed");
                }

                continue;
            }

            $beforeStatus = (string) $order->status;
            $beforeCharge = round((float) ($order->charge_amount ?? 0), 2);

            try {
                $api->syncOrderStatusFromCrmIfConfigured($order);
                $order->refresh();
                $afterStatus = (string) $order->status;
                $afterCharge = round((float) ($order->charge_amount ?? 0), 2);
                if ($beforeStatus !== $afterStatus || $beforeCharge !== $afterCharge) {
                    $changed++;
                    $this->info("FIXED #{$order->id} {$beforeStatus}/{$beforeCharge} => {$afterStatus}/{$afterCharge}");
                    Log::info('orders:reconcile-crm fixed', [
                        'order_id' => $order->id,
                        'before_status' => $beforeStatus,
                        'before_charge' => $beforeCharge,
                        'after_status' => $afterStatus,
                        'after_charge' => $afterCharge,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->error("#{$order->id}: ".$e->getMessage());
                Log::warning('orders:reconcile-crm error', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(50000);
        }

        $this->line("checked={$checked} changed={$changed} dry=".($dry ? '1' : '0'));

        return self::SUCCESS;
    }
}
