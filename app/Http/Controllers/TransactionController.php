<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Support\OrderSourceFilter;
use App\Support\PartnerFinancePool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->isManager()) {
            abort(403, 'Раздел начислений недоступен менеджеру.');
        }

        $baseQuery = Transaction::query()->with([
            'order:id,city_id,source_id,reference_source_id,client_name',
            'order.city:id,name',
            'order.source:id,name',
            'order.referenceSource:id,name,levelion_source_id',
        ]);

        if ($user->hasElevatedAccess()) {
            // все партнёры, но без заявок, снятых с портала (иначе клик даёт 404)
        } elseif (PartnerFinancePool::usesPool($user)) {
            PartnerFinancePool::scopeTransactions($baseQuery);
        } else {
            // Начисления партнёра лежат на user_id владельца — без тяжёлого whereHas(forPortalUser).
            $baseQuery->where('user_id', $user->effectiveOwnerId());
        }

        \App\Support\PartnerSourceAccess::restrictTransactionsToAccessibleOrders($baseQuery, $user);

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('completed_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $baseQuery->whereDate('completed_at', '<=', $request->date_to);
        }

        $sourceOptions = $this->sourceOptionsForUser($user, clone $baseQuery);

        $query = clone $baseQuery;

        if ($request->filled('filter_id')) {
            $query->where('id', 'like', '%'.$request->filter_id.'%');
        }

        if ($request->filled('filter_source')) {
            $filterSource = (string) $request->input('filter_source');
            $query->whereHas('order', function ($orderQuery) use ($filterSource) {
                OrderSourceFilter::apply($orderQuery, $filterSource);
            });
        }

        $transactions = $query->orderByDesc('completed_at')->paginate(100)->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'dateFrom' => $request->date_from,
            'dateTo' => $request->date_to,
            'filterId' => $request->filter_id,
            'filterSource' => $request->input('filter_source'),
            'sourceOptions' => $sourceOptions,
            'currentBalance' => $user->walletBalance(),
        ]);
    }

    /**
     * Опции фильтра источников: только по заказам из выборки начислений (кэш 60с).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Transaction>  $transactionsQuery
     * @return array<string, string>
     */
    private function sourceOptionsForUser(User $user, $transactionsQuery): array
    {
        $cacheKey = 'tx_source_opts:v1:'.$user->id.':'.$user->effectiveOwnerId();

        return Cache::remember($cacheKey, 60, function () use ($transactionsQuery) {
            $orderIds = (clone $transactionsQuery)
                ->whereNotNull('order_id')
                ->distinct()
                ->limit(2000)
                ->pluck('order_id');

            if ($orderIds->isEmpty()) {
                return [];
            }

            return OrderSourceFilter::optionsForOrdersQuery(
                Order::query()->whereIn('id', $orderIds)
            );
        });
    }
}
