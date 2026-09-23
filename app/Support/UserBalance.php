<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class UserBalance
{
    /**
     * Пересчитать баланс пользователя по транзакциям и записать в users.balance.
     */
    public static function sync(User|int $user): float
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        $balance = self::calculate($userId);

        User::query()->whereKey($userId)->update(['balance' => $balance]);

        if ($user instanceof User) {
            $user->balance = $balance;
        }

        return $balance;
    }

    public static function calculate(int $userId): float
    {
        $last = Transaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->value('balance_after');

        if ($last !== null) {
            return round((float) $last);
        }

        // Fallback: сумма операций, если ещё нет balance_after.
        $sum = (float) Transaction::query()
            ->where('user_id', $userId)
            ->sum('amount');

        return round($sum);
    }

    public static function syncAll(): int
    {
        $userIds = Transaction::query()->distinct()->pluck('user_id');
        $count = 0;

        foreach ($userIds as $userId) {
            self::sync((int) $userId);
            $count++;
        }

        // Пользователи без транзакций — баланс 0.
        User::query()
            ->whereNotIn('id', $userIds)
            ->where('balance', '!=', 0)
            ->update(['balance' => 0]);

        return $count;
    }

    /**
     * Начислить сумму и обновить users.balance + balance_after атомарно.
     *
     * @return array{transaction: Transaction, balance: float}
     */
    public static function applyCharge(User $user, int $orderId, float $amount, $completedAt = null): array
    {
        $amount = round($amount);

        return DB::transaction(function () use ($user, $orderId, $amount, $completedAt) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            $previous = Transaction::query()
                ->where('user_id', $locked->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('balance_after');

            $base = $previous !== null ? (float) $previous : 0.0;
            $newBalance = round($base + $amount);

            $completedAt = $completedAt ? Carbon::parse($completedAt) : now();

            $transaction = Transaction::create([
                'user_id' => $locked->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'operation_type' => 'charge',
                'balance_after' => $newBalance,
                'completed_at' => $completedAt,
            ]);

            // Без model events: замки тестовых учёток не должны рвать CRM-начисления.
            User::query()->whereKey($locked->id)->update(['balance' => $newBalance]);
            $locked->balance = $newBalance;
            $user->balance = $newBalance;

            // Единая гарантия: холд 36ч считает closed_local — при начислении дата всегда есть.
            self::ensureOrderClosedLocal($orderId, $completedAt);

            return ['transaction' => $transaction, 'balance' => $newBalance];
        });
    }

    /**
     * Корректировка леджера (ТЗ сторно/перерасчёт): operation_type=correction.
     * amount > 0 — доначисление; amount < 0 — сторно невыплаченного.
     *
     * @param  array{related_transaction_id?: int|null, reason_code?: string|null, idempotency_key?: string|null, reward_rule?: string|null}  $meta
     * @return array{transaction: Transaction, balance: float, duplicate?: bool}
     */
    public static function applyCorrection(User $user, int $orderId, float $amount, $completedAt = null, array $meta = []): array
    {
        $amount = round($amount);
        if ($amount === 0.0) {
            throw new \InvalidArgumentException('correction amount must be non-zero');
        }

        return DB::transaction(function () use ($user, $orderId, $amount, $completedAt, $meta) {
            $idempotencyKey = isset($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : null;
            if ($idempotencyKey !== null && $idempotencyKey !== '' && Schema::hasColumn('transactions', 'idempotency_key')) {
                $existing = Transaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return [
                        'transaction' => $existing,
                        'balance' => (float) $existing->balance_after,
                        'duplicate' => true,
                    ];
                }
            }

            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            $previous = Transaction::query()
                ->where('user_id', $locked->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('balance_after');

            $base = $previous !== null ? (float) $previous : 0.0;
            $newBalance = round($base + $amount);

            $completedAt = $completedAt ? Carbon::parse($completedAt) : now();

            $attrs = [
                'user_id' => $locked->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'operation_type' => 'correction',
                'balance_after' => $newBalance,
                'completed_at' => $completedAt,
            ];
            if (Schema::hasColumn('transactions', 'related_transaction_id') && array_key_exists('related_transaction_id', $meta)) {
                $attrs['related_transaction_id'] = $meta['related_transaction_id'];
            }
            if (Schema::hasColumn('transactions', 'reason_code') && ! empty($meta['reason_code'])) {
                $attrs['reason_code'] = (string) $meta['reason_code'];
            }
            if (Schema::hasColumn('transactions', 'idempotency_key') && $idempotencyKey) {
                $attrs['idempotency_key'] = $idempotencyKey;
            }
            if (Schema::hasColumn('transactions', 'reward_rule') && ! empty($meta['reward_rule'])) {
                $attrs['reward_rule'] = (string) $meta['reward_rule'];
            }

            $transaction = Transaction::create($attrs);

            User::query()->whereKey($locked->id)->update(['balance' => $newBalance]);
            $locked->balance = $newBalance;
            $user->balance = $newBalance;

            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($order) {
                $ledger = (float) Transaction::query()
                    ->where('order_id', $orderId)
                    ->whereIn('operation_type', ['charge', 'correction'])
                    ->sum('amount');
                $order->charge_amount = max(0, round($ledger));
                if ($order->charge_amount > 0 && $order->status !== 'waiting_payment' && $order->type !== 'warranty') {
                    $order->status = 'waiting_payment';
                }
                $order->save();
            }

            return ['transaction' => $transaction, 'balance' => $newBalance];
        });
    }

    /**
     * Проставить orders.closed_local, если пусто (иначе «доступно к выводу» залипает).
     */
    public static function ensureOrderClosedLocal(int $orderId, $fallbackAt = null): bool
    {
        return (bool) DB::transaction(function () use ($orderId, $fallbackAt) {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (! $order || $order->closed_local !== null) {
                return false;
            }

            $at = $fallbackAt ? Carbon::parse($fallbackAt) : now();
            Order::query()->whereKey($orderId)->whereNull('closed_local')->update([
                'closed_local' => $at,
            ]);

            return true;
        });
    }

    /**
     * Догон: заявки с начислением, но без closed_local.
     *
     * @return int число исправленных заявок
     */
    public static function backfillMissingClosedLocal(int $limit = 500): int
    {
        $orderIds = Order::query()
            ->whereNull('closed_local')
            ->where('charge_amount', '>', 0)
            ->whereIn('status', ['waiting_payment', 'completed'])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $fixed = 0;
        foreach ($orderIds as $orderId) {
            $at = Transaction::query()
                ->where('order_id', $orderId)
                ->where('operation_type', 'charge')
                ->orderBy('id')
                ->value('completed_at')
                ?? Transaction::query()
                    ->where('order_id', $orderId)
                    ->where('operation_type', 'charge')
                    ->orderBy('id')
                    ->value('created_at');

            if (! $at) {
                continue;
            }

            if (self::ensureOrderClosedLocal((int) $orderId, $at)) {
                $fixed++;
            }
        }

        return $fixed;
    }

    /**
     * Списать завершённую выплату ровно один раз и связать её с проводкой леджера.
     *
     * @return array{transaction: Transaction, balance: float, created: bool}
     */
    public static function applyWithdrawal(WithdrawalRequest|int $withdrawal): array
    {
        $withdrawalId = $withdrawal instanceof WithdrawalRequest
            ? (int) $withdrawal->id
            : (int) $withdrawal;

        return DB::transaction(function () use ($withdrawalId) {
            $lockedWithdrawal = WithdrawalRequest::query()
                ->lockForUpdate()
                ->findOrFail($withdrawalId);

            if ($lockedWithdrawal->status !== 'completed') {
                throw new \LogicException('Списание возможно только для завершённой выплаты.');
            }

            if ($lockedWithdrawal->ledger_transaction_id !== null) {
                $transaction = Transaction::query()->findOrFail($lockedWithdrawal->ledger_transaction_id);
                $lockedUser = User::query()->findOrFail($lockedWithdrawal->user_id);
                $balance = round((float) Transaction::query()
                    ->where('user_id', $lockedUser->id)
                    ->orderByDesc('id')
                    ->value('balance_after'));

                if (round((float) $lockedUser->balance) !== $balance) {
                    $lockedUser->balance = $balance;
                    $lockedUser->save();
                }

                return [
                    'transaction' => $transaction,
                    'balance' => $balance,
                    'created' => false,
                ];
            }

            $lockedWithdrawal->load(['items.transaction']);
            $byUser = [];
            foreach ($lockedWithdrawal->items as $item) {
                $ownerId = (int) ($item->transaction?->user_id ?? $lockedWithdrawal->user_id);
                $byUser[$ownerId] = ($byUser[$ownerId] ?? 0) + round((float) $item->amount);
            }

            if ($byUser === []) {
                $byUser[(int) $lockedWithdrawal->user_id] = round((float) $lockedWithdrawal->total_amount);
            }

            ksort($byUser);

            $primary = null;
            foreach ($byUser as $ownerId => $amount) {
                $amount = round((float) $amount);
                if ($amount <= 0) {
                    throw new \LogicException('Сумма выплаты должна быть больше нуля.');
                }

                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($ownerId);

                $previous = Transaction::query()
                    ->where('user_id', $lockedUser->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('balance_after');

                $base = $previous !== null ? (float) $previous : 0.0;
                $newBalance = round($base - $amount);

                $transaction = Transaction::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => null,
                    'amount' => -$amount,
                    'operation_type' => 'withdrawal',
                    'balance_after' => $newBalance,
                    'completed_at' => now(),
                ]);

                $lockedUser->balance = $newBalance;
                $lockedUser->save();

                if ($primary === null || (int) $ownerId === (int) $lockedWithdrawal->user_id) {
                    $primary = ['transaction' => $transaction, 'balance' => $newBalance];
                }
            }

            if ($primary === null) {
                throw new \LogicException('Не удалось списать выплату.');
            }

            $lockedWithdrawal->ledger_transaction_id = $primary['transaction']->id;
            $lockedWithdrawal->save();

            return [
                'transaction' => $primary['transaction'],
                'balance' => $primary['balance'],
                'created' => true,
            ];
        });
    }
}
