<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final class PartnerOrdersList
{
    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'all' => 'Все заявки',
        'in_work' => 'В работе',
        'in_work_sd' => 'В работе СД',
        'on_way' => 'В пути',
        'ready' => 'Готов',
        'warranty' => 'Гарантия',
        'clarification' => 'На уточнении',
        'not_processed' => 'Не оформлена',
        'waiting' => 'Ожидает',
        'waiting_payment' => 'Ожидает выплаты',
        'refusal' => 'Отказ',
        'refusal_non_profile' => 'Отказ Непрофиль',
        'cancelled' => 'Отмена',
    ];

    /** Вкладки, которые фильтруют по типу заявки, а не по статусу. */
    private const TYPE_TABS = [
        'warranty' => 'warranty',
    ];

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'first_time' => 'Впервые',
        'warranty' => 'Гарантия',
        'repeat' => 'Повтор',
    ];

    /**
     * @return array{
     *     orders: LengthAwarePaginator,
     *     tabs: list<array{key: string, label: string, count: int}>,
     *     activeStatus: string,
     *     filterOptions: array<string, mixed>,
     *     formAction: string,
     * }
     */
    public static function resolve(Request $request, User $user, string $formAction = '/orders'): array
    {
        $activeStatus = $request->input('status', 'all');
        $statusCounts = self::statusCounts($user);
        $tabs = self::buildTabs($statusCounts);

        $ordersQuery = Order::query()
            ->forPortalUser($user)
            ->with(['city:id,name', 'source:id,name', 'referenceSource:id,name,levelion_source_id', 'employee:id,name', 'workType:id,name', 'user:id,name']);

        if (isset(self::TYPE_TABS[$activeStatus])) {
            $ordersQuery->where('type', self::TYPE_TABS[$activeStatus]);
        } elseif ($activeStatus !== 'all') {
            $ordersQuery->where('status', $activeStatus);
        }

        self::applyFilters($ordersQuery, $request);
        self::applySorting($ordersQuery, $request);

        $orders = $ordersQuery->paginate(100)->withQueryString();

        // FR-REC-05: без N+1 в CRM на отрисовке списка — сверка фоном (orders:reconcile-cursor).

        return [
            'orders' => $orders,
            'tabs' => $tabs,
            'activeStatus' => $activeStatus,
            'filterOptions' => self::filterOptions($user),
            'formAction' => $formAction,
        ];
    }

    /** @return array<string, int> */
    public static function statusCounts(User $user): array
    {
        $rows = Order::query()
            ->forPortalUser($user)
            ->selectRaw("status, COUNT(*) as cnt, SUM(CASE WHEN type = 'warranty' THEN 1 ELSE 0 END) as warranty_cnt")
            ->groupBy('status')
            ->get();

        $counts = [];
        $all = 0;
        $warranty = 0;

        foreach ($rows as $row) {
            $status = (string) $row->status;
            $cnt = (int) $row->cnt;
            $counts[$status] = $cnt;
            $all += $cnt;
            $warranty += (int) $row->warranty_cnt;
        }

        $counts['all'] = $all;
        $counts['warranty'] = $warranty;

        return $counts;
    }

    /**
     * @param  array<string, int>  $statusCounts
     * @return list<array{key: string, label: string, count: int}>
     */
    public static function buildTabs(array $statusCounts): array
    {
        $tabs = [];

        foreach (self::STATUS_LABELS as $key => $label) {
            $tabs[] = [
                'key' => $key,
                'label' => $label,
                'count' => $statusCounts[$key] ?? 0,
            ];
        }

        return $tabs;
    }

    public static function applyFilters($query, Request $request): void
    {
        if ($request->filled('filter_id')) {
            $filterId = trim((string) $request->input('filter_id'));

            $query->where(function ($idQuery) use ($filterId) {
                $idQuery->where('id', 'like', '%'.$filterId.'%');

                if (ctype_digit($filterId)) {
                    $idQuery->orWhere('levelion_order_id', (int) $filterId);
                }
            });
        }

        if ($request->filled('filter_city')) {
            $query->where('city_id', $request->input('filter_city'));
        }

        if ($request->filled('filter_status')) {
            $query->where('status', $request->input('filter_status'));
        }

        if ($request->filled('filter_type')) {
            $query->where('type', $request->input('filter_type'));
        }

        if ($request->filled('filter_source')) {
            OrderSourceFilter::apply($query, (string) $request->input('filter_source'));
        }

        if ($request->filled('filter_name')) {
            $query->where('client_name', 'like', '%'.$request->input('filter_name').'%');
        }

        if ($request->filled('filter_phone')) {
            $query->where('client_phone', 'like', '%'.$request->input('filter_phone').'%');
        }
    }

    public static function applySorting($query, Request $request): void
    {
        $sortable = [
            'id',
            'city_id',
            'status',
            'type',
            'source_id',
            'reference_source_id',
            'order_time',
            'client_name',
            'client_phone',
            'created_local',
            'closed_local',
            'charge_amount',
            'employee_id',
            'work_type_id',
            'user_id',
        ];

        $sortBy = $request->input('sort_by', 'created_local');
        $sortDir = $request->input('sort_dir', 'desc');

        if (in_array($sortBy, $sortable, true) && in_array($sortDir, ['asc', 'desc'], true)) {
            $query->orderBy($sortBy, $sortDir)->orderByDesc('id');
        } else {
            $query->orderByDesc('created_local')->orderByDesc('id');
        }
    }

    /** Жёсткий потолок строк в одном файле экспорта (защита от OOM). */
    public const EXPORT_MAX_ROWS = 10000;

    /** @return array<string, mixed> */
    public static function filterOptions(User $user): array
    {
        $cacheKey = 'portal_order_filter_opts:v2:'.$user->id.':'.$user->effectiveOwnerId();

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $orders = Order::query()->forPortalUser($user);
            $cities = PortalCityOptions::cityMapForOrderFilters($user, $orders);
            $statuses = (clone $orders)->whereNotNull('status')->distinct()->pluck('status')->toArray();
            $types = (clone $orders)->whereNotNull('type')->distinct()->pluck('type')->toArray();
            $sources = OrderSourceFilter::optionsForOrdersQuery($orders);

            return [
                'cities' => $cities,
                'statuses' => collect($statuses)->mapWithKeys(fn ($s) => [$s => self::STATUS_LABELS[$s] ?? $s])->toArray(),
                'types' => collect($types)->mapWithKeys(fn ($t) => [$t => self::TYPE_LABELS[$t] ?? $t])->toArray(),
                'sources' => $sources,
            ];
        });
    }
}
