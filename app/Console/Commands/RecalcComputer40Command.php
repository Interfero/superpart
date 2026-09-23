<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequestItem;
use App\Services\LevelionApiService;
use App\Support\PartnerChargeCalculator;
use App\Support\UserBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Перерасчёт computer 40% с 2026-09-09 MSK (ТЗ §3.6–3.8).
 */
class RecalcComputer40Command extends Command
{
    protected $signature = 'finance:recalc-computer-40
                            {--dry-run : Только отчёт}
                            {--limit=200 : Максимум заявок}';

    protected $description = 'Dry-run/apply доначислений computer 40% vs текущий леджер';

    public function handle(LevelionApiService $api): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $since = PartnerChargeCalculator::computerRuleStartsAt();

        $orders = Order::query()
            ->whereRaw("LOWER(TRIM(COALESCE(equipment_type, ''))) = ?", ['computer'])
            ->where('charge_amount', '>', 0)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $rows = [];
        $totalDelta = 0;

        foreach ($orders as $order) {
            $crmId = (int) ($order->levelion_order_id ?: $order->id);
            $paid = null;
            $parts = null;
            $createdAt = $order->created_local ?? $order->order_time;

            if ($api->isConfigured()) {
                $data = $api->fetchPartnerOrderFromCrm($crmId, 8);
                if (is_array($data) && (array_key_exists('amount_paid', $data) || array_key_exists('paid_amount', $data))) {
                    $paid = (int) ($data['amount_paid'] ?? $data['paid_amount'] ?? 0);
                    $parts = (int) ($data['amount_comp'] ?? $data['amount_parts'] ?? 0);
                    if (! empty($data['order_created_at'])) {
                        $createdAt = Carbon::parse($data['order_created_at']);
                    } elseif (! empty($data['created_at'])) {
                        $createdAt = Carbon::parse($data['created_at']);
                    }
                }
            }

            if ($paid === null) {
                $rows[] = [
                    'id' => $order->id,
                    'skip' => 'no_crm_amounts',
                ];
                continue;
            }

            if (! PartnerChargeCalculator::usesComputerRate('computer', $createdAt)) {
                continue;
            }

            $expected = (int) PartnerChargeCalculator::fromPaidAndParts($paid, $parts, 'computer', $createdAt);
            $ledger = (int) round((float) Transaction::query()
                ->where('order_id', $order->id)
                ->whereIn('operation_type', ['charge', 'correction'])
                ->sum('amount'));

            $delta = $expected - $ledger;
            if ($delta === 0) {
                continue;
            }

            $chargeTx = Transaction::query()
                ->where('order_id', $order->id)
                ->where('operation_type', 'charge')
                ->orderBy('id')
                ->first();

            $withdrawn = false;
            if ($chargeTx) {
                $withdrawn = WithdrawalRequestItem::query()
                    ->where('transaction_id', $chargeTx->id)
                    ->whereHas('withdrawalRequest', fn ($q) => $q->where('status', 'completed'))
                    ->exists();
            }

            $rows[] = [
                'id' => $order->id,
                'paid' => $paid,
                'parts' => $parts,
                'expected' => $expected,
                'ledger' => $ledger,
                'delta' => $delta,
                'withdrawn' => $withdrawn,
            ];

            if ($withdrawn) {
                continue;
            }

            $totalDelta += $delta;

            if (! $dry && $delta !== 0) {
                $user = User::query()->find((int) $order->user_id);
                if (! $user) {
                    continue;
                }
                UserBalance::applyCorrection($user, (int) $order->id, (float) $delta);
                $this->info("APPLY #{$order->id} delta={$delta}");
            }
        }

        $this->table(
            ['id', 'paid', 'parts', 'expected', 'ledger', 'delta', 'withdrawn', 'skip'],
            collect($rows)->map(fn ($r) => [
                $r['id'] ?? '',
                $r['paid'] ?? '',
                $r['parts'] ?? '',
                $r['expected'] ?? '',
                $r['ledger'] ?? '',
                $r['delta'] ?? '',
                isset($r['withdrawn']) ? ($r['withdrawn'] ? 'yes' : 'no') : '',
                $r['skip'] ?? '',
            ])->all()
        );

        $this->line('candidates='.count($rows).' total_delta_applicable='.$totalDelta.' dry='.($dry ? '1' : '0'));

        return self::SUCCESS;
    }
}
