<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;
use App\Support\PartnerSourceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class WithdrawableTransactions
{
    public const COOLING_HOURS = 36;

    /**
     * Начисления, которые уже прошли период охлаждения и ещё не зарезервированы выплатой.
     *
     * @return Builder<Transaction>
     */
    public static function queryFor(User $user): Builder
    {
        $cutoff = now()->subHours(self::COOLING_HOURS);

        $query = Transaction::query()
            ->where('transactions.operation_type', 'charge')
            ->where('transactions.amount', '>', 0)
            ->whereExists(function ($orderExists) use ($cutoff) {
                $orderExists
                    ->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.id', 'transactions.order_id')
                    ->where('orders.charge_amount', '>', 0)
                    ->whereNotIn('orders.status', [
                        'refusal',
                        'refusal_non_profile',
                        'cancelled',
                        'warranty',
                    ])
                    // Охлаждение от даты закрытия; если closed_local забыли проставить — от даты начисления.
                    ->whereRaw(
                        'COALESCE(orders.closed_local, transactions.completed_at, transactions.created_at) <= ?',
                        [$cutoff]
                    );
                if (Schema::hasColumn('orders', 'excluded_at')) {
                    $orderExists->whereNull('orders.excluded_at');
                }
            })
            ->whereNotExists(function ($reserved) {
                $reserved
                    ->selectRaw('1')
                    ->from('withdrawal_request_items as reserved_items')
                    ->whereColumn('reserved_items.transaction_id', 'transactions.id');
            });

        if ($user->isManager()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasElevatedAccess()) {
            return PartnerSourceAccess::restrictTransactionsToAccessibleOrders($query, $user);
        }

        if (PartnerFinancePool::usesPool($user)) {
            PartnerFinancePool::scopeTransactions($query);

            return PartnerSourceAccess::restrictTransactionsToAccessibleOrders($query, $user);
        }

        $query->where('transactions.user_id', $user->effectiveOwnerId());

        return PartnerSourceAccess::restrictTransactionsToAccessibleOrders($query, $user);
    }
}
