<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\PartnerOrdersList;
use App\Support\WithdrawableTransactions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $now = Carbon::now();
        $ownerId = $user->effectiveOwnerId();

        $ranges = [
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
        ];

        $labels = [
            'today' => 'Сегодня',
            'yesterday' => 'Вчера',
            'this_week' => 'Эта неделя',
            'this_month' => 'Этот месяц',
            'last_month' => 'Прошлый месяц',
        ];

        // При общих источниках — общий пул партнёров (одинаковые суммы у всех).
        // Иначе — только кошелёк владельца. Elevated — все начисления.
        $chargeQuery = Transaction::query()->where('operation_type', 'charge');
        if ($user->hasElevatedAccess()) {
            \App\Support\PartnerSourceAccess::restrictTransactionsToAccessibleOrders($chargeQuery, $user);
        } elseif (\App\Support\PartnerFinancePool::usesPool($user)) {
            \App\Support\PartnerFinancePool::scopeTransactions($chargeQuery);
            \App\Support\PartnerSourceAccess::restrictTransactionsToAccessibleOrders($chargeQuery, $user);
        } else {
            $chargeQuery->where('user_id', $ownerId);
            \App\Support\PartnerSourceAccess::restrictTransactionsToAccessibleOrders($chargeQuery, $user);
        }

        $financeRow = (clone $chargeQuery)
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(completed_at, created_at) BETWEEN ? AND ? THEN amount ELSE 0 END) as today_amount,
                 SUM(CASE WHEN COALESCE(completed_at, created_at) BETWEEN ? AND ? THEN amount ELSE 0 END) as yesterday_amount,
                 SUM(CASE WHEN COALESCE(completed_at, created_at) BETWEEN ? AND ? THEN amount ELSE 0 END) as this_week_amount,
                 SUM(CASE WHEN COALESCE(completed_at, created_at) BETWEEN ? AND ? THEN amount ELSE 0 END) as this_month_amount,
                 SUM(CASE WHEN COALESCE(completed_at, created_at) BETWEEN ? AND ? THEN amount ELSE 0 END) as last_month_amount',
                [
                    $ranges['today'][0], $ranges['today'][1],
                    $ranges['yesterday'][0], $ranges['yesterday'][1],
                    $ranges['this_week'][0], $ranges['this_week'][1],
                    $ranges['this_month'][0], $ranges['this_month'][1],
                    $ranges['last_month'][0], $ranges['last_month'][1],
                ]
            )
            ->first();

        $financeStats = [];
        foreach ($labels as $key => $label) {
            $financeStats[$key] = [
                'label' => $label,
                'amount' => (float) ($financeRow?->{$key.'_amount'} ?? 0),
            ];
        }

        $withdrawalStats = null;

        if (! $user->isManager() && Gate::allows('use-withdrawals')) {
            $withdrawalBase = WithdrawalRequest::query()->from('withdrawal_requests');
            $isElevated = $user->hasElevatedAccess();
            $usesPool = \App\Support\PartnerFinancePool::usesPool($user);

            if ($isElevated) {
                // без фильтра
            } elseif ($usesPool) {
                \App\Support\PartnerFinancePool::scopeWithdrawals($withdrawalBase);
            } else {
                // Явно withdrawal_requests.user_id — иначе после JOIN с transactions колонка ambiguous.
                $withdrawalBase->where('withdrawal_requests.user_id', $ownerId);
            }

            $availableForWithdrawal = (float) WithdrawableTransactions::queryFor($user)->sum('amount');

            // Всегда синхронизируем баланс с леджером перед показом (после выплат/бэкапов).
            if (! $isElevated && ! $usesPool) {
                \App\Support\UserBalance::sync($ownerId);
                $user->refresh();
            }

            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();

            $completedMonth = (float) (clone $withdrawalBase)
                ->where('withdrawal_requests.status', 'completed')
                ->whereNotNull('withdrawal_requests.ledger_transaction_id')
                ->join('transactions', 'transactions.id', '=', 'withdrawal_requests.ledger_transaction_id')
                ->whereBetween('transactions.completed_at', [$monthStart, $monthEnd])
                ->sum('withdrawal_requests.total_amount');

            $withdrawalStats = [
                'balance' => (float) $user->walletBalance(),
                'available' => $availableForWithdrawal,
                'in_work_amount' => (float) (clone $withdrawalBase)
                    ->where('withdrawal_requests.status', 'in_work')
                    ->sum('withdrawal_requests.total_amount'),
                'completed_month' => $completedMonth,
                'is_elevated' => $isElevated,
                'is_shared_pool' => $usesPool,
            ];
        }

        $ordersList = PartnerOrdersList::resolve($request, $user, route('home'));

        return view('dashboard.index', array_merge(
            compact('financeStats', 'withdrawalStats'),
            $ordersList
        ));
    }
}
