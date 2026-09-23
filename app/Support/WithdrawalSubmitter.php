<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalRequestItem;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class WithdrawalSubmitter
{
    public const IDEMPOTENT_MINUTES = 15;

    /**
     * @param  list<int>  $transactionIds
     * @return Collection<int, WithdrawalRequest>
     */
    public static function submit(
        User $actor,
        array $transactionIds,
        string $requisites,
        ?string $bankCardLabel,
        mixed $recipientBirthDate,
        ?string $comment
    ): Collection {
        $transactionIds = array_values(array_unique(array_map('intval', $transactionIds)));

        try {
            return DB::transaction(function () use ($actor, $transactionIds, $requisites, $bankCardLabel, $recipientBirthDate, $comment) {
                return self::submitLocked(
                    $actor,
                    $transactionIds,
                    $requisites,
                    $bankCardLabel,
                    $recipientBirthDate,
                    $comment
                );
            });
        } catch (UniqueConstraintViolationException $e) {
            $existing = self::findRecentCovering($transactionIds);
            if ($existing->isNotEmpty()) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'transaction_ids' => 'Эти начисления уже попали в другую заявку на выплату. Обновите список.',
            ]);
        } catch (QueryException $e) {
            if (! self::isDuplicateItemConstraint($e)) {
                throw $e;
            }

            $existing = self::findRecentCovering($transactionIds);
            if ($existing->isNotEmpty()) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'transaction_ids' => 'Эти начисления уже попали в другую заявку на выплату. Обновите список.',
            ]);
        }
    }

    /**
     * @param  list<int>  $transactionIds
     * @return Collection<int, WithdrawalRequest>
     */
    private static function submitLocked(
        User $actor,
        array $transactionIds,
        string $requisites,
        ?string $bankCardLabel,
        mixed $recipientBirthDate,
        ?string $comment
    ): Collection {
        Transaction::query()
            ->whereIn('id', $transactionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        $available = WithdrawableTransactions::queryFor($actor)
            ->whereIn('transactions.id', $transactionIds)
            ->with(['order.city', 'order.source', 'order.referenceSource'])
            ->orderBy('transactions.id')
            ->get();

        if ($available->count() === count($transactionIds)) {
            return collect([
                self::createSingle(
                    $actor,
                    $available,
                    $requisites,
                    $bankCardLabel,
                    $recipientBirthDate,
                    $comment
                ),
            ]);
        }

        $existing = self::findRecentCovering($transactionIds);
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        throw ValidationException::withMessages([
            'transaction_ids' => self::unavailableMessage($actor, $transactionIds, $available->pluck('id')->all()),
        ]);
    }

    /**
     * Одна заявка на выплату — один кошелёк получателя (кто указал реквизиты).
     * Начисления разных партнёров остаются строками внутри, без отдельных заявок.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private static function createSingle(
        User $actor,
        Collection $transactions,
        string $requisites,
        ?string $bankCardLabel,
        mixed $recipientBirthDate,
        ?string $comment
    ): WithdrawalRequest {
        if ($transactions->isEmpty()) {
            throw ValidationException::withMessages([
                'transaction_ids' => 'Не найдено подходящих начислений',
            ]);
        }

        $partnerIds = $transactions->pluck('user_id')->unique()->filter()->values();
        if ($partnerIds->count() > 1 && ! $actor->hasElevatedAccess() && ! PartnerFinancePool::usesPool($actor)) {
            throw ValidationException::withMessages([
                'transaction_ids' => 'Выберите начисления только одного партнёра за раз.',
            ]);
        }

        $walletUserId = $actor->hasElevatedAccess()
            ? (int) $actor->id
            : (int) $actor->effectiveOwnerId();

        $payload = [
            'user_id' => $walletUserId,
            'status' => 'in_work',
            'total_amount' => $transactions->sum('amount'),
            'bank_card' => $bankCardLabel,
            'recipient_birth_date' => $recipientBirthDate,
            'requisites' => $requisites,
            'comment' => $comment,
        ];

        if (Schema::hasColumn('withdrawal_requests', 'batch_id')) {
            $payload['batch_id'] = null;
        }

        $withdrawal = WithdrawalRequest::create($payload);

        foreach ($transactions as $transaction) {
            WithdrawalRequestItem::create([
                'withdrawal_request_id' => $withdrawal->id,
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->amount,
                'city_id' => $transaction->order->city_id ?? 1,
                'status' => 'waiting_payment',
                'created_local' => $transaction->completed_at,
            ]);
        }

        return $withdrawal;
    }

    /**
     * @param  list<int>  $transactionIds
     * @return Collection<int, WithdrawalRequest>
     */
    public static function findRecentCovering(array $transactionIds): Collection
    {
        $transactionIds = array_values(array_unique(array_map('intval', $transactionIds)));
        if ($transactionIds === []) {
            return collect();
        }

        $itemRows = WithdrawalRequestItem::query()
            ->whereIn('transaction_id', $transactionIds)
            ->get(['transaction_id', 'withdrawal_request_id']);

        if ($itemRows->count() !== count($transactionIds)) {
            return collect();
        }

        $covered = $itemRows->pluck('transaction_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $requested = collect($transactionIds)->sort()->values();
        if ($covered->all() !== $requested->all()) {
            return collect();
        }

        $withdrawals = WithdrawalRequest::query()
            ->whereIn('id', $itemRows->pluck('withdrawal_request_id')->unique()->all())
            ->get();

        if ($withdrawals->isEmpty()) {
            return collect();
        }

        $fresh = $withdrawals->every(function (WithdrawalRequest $withdrawal) {
            return $withdrawal->status === 'in_work'
                && $withdrawal->created_at !== null
                && $withdrawal->created_at->gte(now()->subMinutes(self::IDEMPOTENT_MINUTES));
        });

        return $fresh ? $withdrawals->sortBy('id')->values() : collect();
    }

    /**
     * @param  list<int>  $requestedIds
     * @param  list<int|string>  $availableIds
     */
    public static function unavailableMessage(User $actor, array $requestedIds, array $availableIds): string
    {
        $missing = array_values(array_diff(
            array_map('intval', $requestedIds),
            array_map('intval', $availableIds)
        ));

        $reservedOrderIds = [];
        $coolingOrderIds = [];
        $unknown = [];
        $cutoff = now()->subHours(WithdrawableTransactions::COOLING_HOURS);

        if ($missing !== []) {
            $rows = Transaction::query()
                ->whereIn('transactions.id', $missing)
                ->leftJoin('orders', 'orders.id', '=', 'transactions.order_id')
                ->get([
                    'transactions.id',
                    'transactions.order_id',
                    'orders.closed_local',
                    'transactions.completed_at',
                    'transactions.created_at',
                ]);

            $reservedTx = WithdrawalRequestItem::query()
                ->whereIn('transaction_id', $missing)
                ->pluck('transaction_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($missing as $id) {
                $row = $rows->firstWhere('id', $id);
                if (! $row) {
                    $unknown[] = $id;
                    continue;
                }

                $orderId = (int) ($row->order_id ?: $id);

                if (in_array($id, $reservedTx, true)) {
                    $reservedOrderIds[] = $orderId;
                    continue;
                }

                $closedAt = $row->closed_local ?? $row->completed_at ?? $row->created_at;
                if ($closedAt && $closedAt > $cutoff) {
                    $coolingOrderIds[] = $orderId;
                    continue;
                }

                $unknown[] = $orderId;
            }
        }

        return self::formatUnavailableMessage($coolingOrderIds, $reservedOrderIds, $unknown);
    }

    /**
     * @param  list<int>  $coolingOrderIds
     * @param  list<int>  $reservedOrderIds
     * @param  list<int>  $unknownIds
     */
    public static function formatUnavailableMessage(array $coolingOrderIds, array $reservedOrderIds, array $unknownIds = []): string
    {
        $parts = [];

        if ($reservedOrderIds !== []) {
            $parts[] = 'Заявки уже в другой выплате: '.implode(', ', array_unique($reservedOrderIds));
        }

        if ($coolingOrderIds !== []) {
            $parts[] = 'Ещё не прошло '.WithdrawableTransactions::COOLING_HOURS.' часов после закрытия: '.implode(', ', array_unique($coolingOrderIds));
        }

        if ($unknownIds !== []) {
            $parts[] = 'Начисления недоступны для вывода: '.implode(', ', array_unique($unknownIds));
        }

        if ($parts === []) {
            return 'Часть начислений недоступна для вывода. Обновите список.';
        }

        return implode('. ', $parts).'. Такие заявки не показываются в списке к выводу.';
    }

    private static function isDuplicateItemConstraint(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'withdrawal_items_transaction_unique')
            || str_contains($message, 'Duplicate entry');
    }
}
