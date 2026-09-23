<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Support\PartnerChargeCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Перерасчёт computer 40% с 2026-09-09 (ТЗ FR-FIN-03).
 * Без --dry-run команда отказывается писать.
 */
class BackfillComputer40Command extends Command
{
    protected $signature = 'finance:backfill-computer-40
                            {--dry-run : Обязательный отчёт без записи}
                            {--apply : Применить corrections (только после dry-run и подтверждения)}
                            {--limit=500 : Лимит заявок за прогон}';

    protected $description = 'Dry-run / apply перерасчёта computer 40% vs freeze';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if (! $dry && ! $apply) {
            $this->error('Укажите --dry-run или --apply (после подтверждения dry-run).');

            return self::FAILURE;
        }
        if ($dry && $apply) {
            $this->error('Нельзя одновременно --dry-run и --apply.');

            return self::FAILURE;
        }

        $cutoff = Carbon::parse(PartnerChargeCalculator::COMPUTER_CUTOFF_MSK, 'Europe/Moscow');
        $limit = max(1, (int) $this->option('limit'));

        $orders = Order::query()
            ->where('equipment_type', 'computer')
            ->where('type', '!=', 'warranty')
            ->whereNotNull('closed_local')
            ->where('closed_local', '>=', $cutoff->copy()->timezone(config('app.timezone')))
            ->where(function ($q) use ($cutoff) {
                $q->where('created_local', '>=', $cutoff->copy()->timezone(config('app.timezone')))
                    ->orWhereNull('created_local');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = [
            'candidates' => 0,
            'unchanged' => 0,
            'delta_pos' => 0.0,
            'delta_neg' => 0.0,
            'delta_pos_count' => 0,
            'delta_neg_count' => 0,
            'paid_out' => 0,
            'unpaid' => 0,
            'disputed' => 0,
            'skipped' => 0,
            'applied' => 0,
        ];

        foreach ($orders as $order) {
            $created = $order->created_local ?? $order->order_time;
            if (! PartnerChargeCalculator::isComputerRule($order->equipment_type, $created)) {
                $stats['skipped']++;
                continue;
            }

            $chargeTx = Transaction::query()
                ->where('order_id', $order->id)
                ->where('operation_type', 'charge')
                ->orderBy('id')
                ->first();

            if (! $chargeTx) {
                $stats['skipped']++;
                continue;
            }

            $corrections = (float) Transaction::query()
                ->where('order_id', $order->id)
                ->where('operation_type', 'correction')
                ->sum('amount');

            $effective = round((float) $chargeTx->amount + $corrections);
            // Без CRM amount_paid локально — оцениваем expected из текущего charge back-calc невозможно.
            // Берём expected из PartnerChargeCalculator только если в order есть кэш сумм — иначе skip.
            // SP orders не хранят amount_paid; expected берём из CRM через levelion если нужно.
            // Для dry-run: expected = recalculated from charge_amount ratio is wrong.
            // Используем crm snapshot fields if present on order — иначе пропускаем без paid/parts.
            $stats['candidates']++;

            // Попытка: из LevelionApiService fetch — слишком тяжело для dry-run без сети.
            // Вместо этого: если charge уже по 30%, и есть net в комментарии — нет.
            // Прагматично: expected считаем только когда crm_checksum payload в inbox содержит суммы.
            $inbox = DB::table('sync_inbox')
                ->where('order_id', $order->id)
                ->where('result', 'applied')
                ->orderByDesc('id')
                ->first();

            $paid = null;
            $parts = null;
            if ($inbox && $inbox->payload) {
                $payload = json_decode((string) $inbox->payload, true);
                $block = is_array($payload) ? ($payload['order'] ?? []) : [];
                if (is_array($block)) {
                    $paid = $block['amount_paid'] ?? null;
                    $parts = $block['amount_parts'] ?? $block['amount_comp'] ?? null;
                }
            }

            if ($paid === null) {
                $stats['disputed']++;
                continue;
            }

            $expected = (float) PartnerChargeCalculator::fromPaidAndParts(
                $paid,
                $parts,
                'computer',
                $created,
            );
            $delta = round($expected - $effective);

            if ($delta === 0.0) {
                $stats['unchanged']++;
                continue;
            }

            $paidOut = $this->isChargePaidOut((int) $order->id, (int) $chargeTx->id);
            if ($paidOut) {
                $stats['paid_out']++;
                $stats['disputed']++;
                continue;
            }

            $stats['unpaid']++;
            if ($delta > 0) {
                $stats['delta_pos'] += $delta;
                $stats['delta_pos_count']++;
            } else {
                $stats['delta_neg'] += $delta;
                $stats['delta_neg_count']++;
            }

            if ($apply) {
                // Применение — отдельным шагом после подтверждения; здесь только каркас.
                $this->line("WOULD_APPLY order={$order->id} effective={$effective} expected={$expected} delta={$delta}");
            } else {
                $this->line("DIFF order={$order->id} effective={$effective} expected={$expected} delta={$delta}");
            }
        }

        $this->newLine();
        $this->info('computer-40 backfill '.($dry ? 'DRY-RUN' : 'APPLY-SCAN'));
        foreach ($stats as $k => $v) {
            $this->line("{$k}={$v}");
        }

        if ($dry) {
            $this->warn('Запись не выполнялась. Для применения нужно письменное подтверждение и --apply.');
        }

        return self::SUCCESS;
    }

    private function isChargePaidOut(int $orderId, int $chargeTxId): bool
    {
        $item = DB::table('withdrawal_request_items')
            ->where('transaction_id', $chargeTxId)
            ->first();
        if (! $item) {
            return false;
        }
        $wd = WithdrawalRequest::query()->find($item->withdrawal_request_id);

        return $wd && $wd->status === 'completed';
    }
}
