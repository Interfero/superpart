<?php

namespace App\Http\Controllers;

use App\Exports\OrdersExport;
use App\Models\Order;
use App\Models\ReferenceSource;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use App\Models\WorkType;
use App\Support\OrderEquipment;
use App\Support\OrderSourceFilter;
use App\Support\PartnerOrdersList;
use App\Support\PortalCityOptions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    private const STATUS_LABELS = [
        'waiting' => 'Ожидает',
        'waiting_payment' => 'Ожидает выплаты',
        'in_work' => 'В работе',
        'in_work_sd' => 'В работе СД',
        'on_way' => 'В пути',
        'ready' => 'Готов',
        'clarification' => 'На уточнении',
        'not_processed' => 'Не оформлена',
        'refusal' => 'Отказ',
        'refusal_non_profile' => 'Отказ Непрофиль',
        'cancelled' => 'Отмена',
        'warranty' => 'Гарантия',
    ];

    private const TYPE_LABELS = [
        'first_time' => 'Впервые',
        'warranty' => 'Гарантия',
        'repeat' => 'Повтор',
    ];

    private const PROFILE_LABELS = [
        '1' => 'Да',
        '0' => 'Нет',
    ];

    private function denyManagers(Request $request): void
    {
        if ($request->user()?->isManager()) {
            abort(403);
        }
    }

    public function cities(Request $request)
    {
        $this->denyManagers($request);
        $this->applyDefaultCurrentMonth($request);

        $user = $request->user();
        $query = Order::query()->forPortalUser($user);

        if ($request->filled('date_from')) {
            $query->whereDate('order_time', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_time', '<=', $request->input('date_to'));
        }

        $chargeQuery = clone $query;
        if ($user->hasElevatedAccess()) {
            // все
        } elseif (\App\Support\PartnerFinancePool::usesPool($user)) {
            $chargeQuery->whereIn('user_id', \App\Support\PartnerFinancePool::partnerIds());
        } else {
            $chargeQuery->where('user_id', $user->effectiveOwnerId());
        }

        $cityStats = (clone $query)
            ->select('city_id')
            ->selectRaw('COUNT(*) as accepted_count')
            ->selectRaw('SUM(closed_local IS NOT NULL) as closed_count')
            ->groupBy('city_id')
            ->with('city')
            ->get();

        $chargesByCity = (clone $chargeQuery)
            ->select('city_id')
            ->selectRaw('SUM(CASE WHEN charge_amount > 0 THEN charge_amount ELSE 0 END) as total_charge')
            ->groupBy('city_id')
            ->pluck('total_charge', 'city_id');

        $cityStats = $cityStats
            ->map(fn ($row) => [
                'city_name' => $row->city->name ?? '—',
                'accepted_count' => (int) $row->accepted_count,
                'closed_count' => (int) $row->closed_count,
                'total_charge' => (float) ($chargesByCity[$row->city_id] ?? 0),
            ])
            ->sortBy('city_name')
            ->values();

        $totals = [
            'accepted_count' => $cityStats->sum('accepted_count'),
            'closed_count' => $cityStats->sum('closed_count'),
            'total_charge' => $cityStats->sum('total_charge'),
        ];

        return view('reports.cities', compact('cityStats', 'totals'));
    }

    public function workTypes(Request $request)
    {
        $this->denyManagers($request);
        $this->applyDefaultCurrentMonth($request);

        $user = $request->user();
        $baseQuery = Order::query()->forPortalUser($user);

        $equipmentTypes = (clone $baseQuery)
            ->whereNotNull('equipment_type')
            ->distinct()
            ->pluck('equipment_type')
            ->filter()
            ->mapWithKeys(fn (string $code) => [$code => OrderEquipment::label($code)])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();

        $sourceOptions = OrderSourceFilter::optionsForOrdersQuery(clone $baseQuery);

        $query = (clone $baseQuery)->with([
            'city',
            'source',
            'referenceSource',
            'workType',
            'employee',
            'user',
        ]);

        $this->applyDateFilters($query, $request);

        if ($request->filled('equipment_type')) {
            $query->where('equipment_type', (string) $request->input('equipment_type'));
        }

        if ($request->filled('filter_source')) {
            OrderSourceFilter::apply($query, (string) $request->input('filter_source'));
        }

        $summaryQuery = clone $query;

        $chargeSummary = clone $summaryQuery;
        if ($user->hasElevatedAccess()) {
            // все
        } elseif (\App\Support\PartnerFinancePool::usesPool($user)) {
            $chargeSummary->whereIn('user_id', \App\Support\PartnerFinancePool::partnerIds());
        } else {
            $chargeSummary->where('user_id', $user->effectiveOwnerId());
        }

        $orders = $query
            ->orderByDesc('created_local')
            ->paginate(500)
            ->withQueryString();

        return view('reports.work-types', [
            'orders' => $orders,
            'equipmentTypes' => $equipmentTypes,
            'sourceOptions' => $sourceOptions,
            'totals' => [
                'orders_count' => (clone $summaryQuery)->count(),
                'closed_count' => (clone $summaryQuery)->whereNotNull('closed_local')->count(),
                'total_charge' => (float) (clone $chargeSummary)->sum('charge_amount'),
            ],
        ]);
    }

    public function orders(Request $request)
    {
        $this->applyDefaultCurrentMonth($request);

        $user = $request->user();
        $query = $this->buildOrdersReportQuery($request, $user);

        $orders = $query
            ->paginate(500)
            ->withQueryString();

        return view('reports.orders', [
            'orders' => $orders,
            'tabs' => [
                [
                    'key' => 'all',
                    'label' => 'Все',
                ],
                [
                    'key' => 'closed',
                    'label' => 'Закрытые',
                ],
            ],
            'activeTab' => $request->input('status', 'all'),
            'filterOptions' => $this->getFilterOptions($this->reportScopeUser($user)),
            'workTypes' => WorkType::query()->orderBy('name')->get(),
        ]);
    }

    public function exportOrders(Request $request)
    {
        $this->applyDefaultCurrentMonth($request);

        $format = $request->input('format', 'xlsx');
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            abort(422, 'Неподдерживаемый формат экспорта.');
        }

        $user = $request->user();
        $query = $this->buildOrdersReportQuery($request, $user);

        $count = (clone $query)->count();
        if ($count > PartnerOrdersList::EXPORT_MAX_ROWS) {
            abort(
                422,
                'Слишком много заявок для экспорта ('.$count.'). Сузьте период или фильтры (лимит '.PartnerOrdersList::EXPORT_MAX_ROWS.').'
            );
        }

        $orders = $query->get();

        return (new OrdersExport($orders, includeCharges: ! $user->isManager()))->download($format);
    }

    /** Менеджер в отчёте смотрит заявки партнёра-владельца, не чужих партнёров. */
    private function reportScopeUser(User $user): User
    {
        if (! $user->isManager()) {
            return $user;
        }

        $ownerId = $user->effectiveOwnerId();
        if ($ownerId === (int) $user->id) {
            return $user;
        }

        return User::query()->find($ownerId) ?? $user;
    }

    private function buildOrdersReportQuery(Request $request, User $user)
    {
        $scopeUser = $this->reportScopeUser($user);

        $query = Order::query()
            ->forPortalUser($scopeUser, applyManagerWindow: false)
            ->with(['city', 'source', 'referenceSource', 'workType', 'employee', 'user']);

        // Поиск по ID заявки — без фильтра дат: иначе «129» не находится вне текущего месяца.
        if (! $request->filled('filter_id')) {
            $this->applyDateFilters($query, $request);
        }

        $this->applyOrdersTableFilters($query, $request);

        $activeTab = $request->input('status', 'all');

        if ($activeTab === 'closed') {
            $query->whereNotNull('closed_local');
        }

        if ($request->filled('work_type')) {
            $query->where('work_type_id', $request->integer('work_type'));
        }

        PartnerOrdersList::applySorting($query, $request);

        return $query;
    }

    public function reviews(Request $request)
{
    $this->denyManagers($request);

    /** @var User $user */
    $user = $request->user();

    $query = Review::query()
        ->forPortalUser($user)
        ->with(['order.city', 'order.source', 'order.referenceSource', 'order.workType', 'city']);

    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    $reviewStats = $query
        ->get()
        ->groupBy(function (Review $review) {
            $order = $review->order;
            $source = $order?->sourceDisplayName() ?? '—';
            $equipment = $order?->equipment_type
                ? OrderEquipment::label($order->equipment_type)
                : ($order?->workType?->name ?? $order?->serverTypeLabel() ?? '—');
            $city = $review->city?->name ?? $order?->city?->name ?? '—';

            return $source.'|'.$equipment.'|'.$city;
        })
        ->map(function ($items, $key) {
            [$source, $equipment, $city] = array_pad(explode('|', (string) $key, 3), 3, '—');

            $total = $items->count();
            $resolved = $items->where('status', 'resolved')->count();
            $notResolved = $items->whereIn('status', ['new', 'not_resolved'])->count();

            return [
                'source_name' => $source,
                'equipment_name' => $equipment,
                'city_name' => $city,
                'orders_count' => $total,
                'resolved_count' => $resolved,
                'not_resolved_count' => $notResolved,
            ];
        })
        ->sortBy(fn (array $row) => $row['source_name'].'|'.$row['city_name'])
        ->values();

    $totals = [
        'orders_count' => $reviewStats->sum('orders_count'),
        'resolved_count' => $reviewStats->sum('resolved_count'),
        'not_resolved_count' => $reviewStats->sum('not_resolved_count'),
    ];

    return view('reports.reviews', [
        'reviewStats' => $reviewStats,
        'totals' => $totals,
    ]);
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->filled('date_from') && $request->filled('date_to')
            && $request->input('date_from') > $request->input('date_to')) {
            throw ValidationException::withMessages([
                'date_to' => 'Дата «по» не может быть раньше даты «от».',
            ]);
        }

        if ($request->filled('closed_from') && $request->filled('closed_to')
            && $request->input('closed_from') > $request->input('closed_to')) {
            throw ValidationException::withMessages([
                'closed_to' => '«Закрыто по» не может быть раньше «Закрыто от».',
            ]);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('order_time', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_time', '<=', $request->input('date_to'));
        }

        if ($request->filled('closed_from')) {
            $query->whereDate('closed_local', '>=', $request->input('closed_from'));
        }

        if ($request->filled('closed_to')) {
            $query->whereDate('closed_local', '<=', $request->input('closed_to'));
        }
    }

    private function applyDefaultCurrentMonth(Request $request): void
    {
        // has() ломается на пустых date_from/date_to из формы → «всё время» вместо месяца.
        if ($request->filled('date_from') || $request->filled('date_to')) {
            return;
        }

        $request->merge([
            'date_from' => Carbon::now()->startOfMonth()->toDateString(),
            'date_to' => Carbon::now()->endOfMonth()->toDateString(),
        ]);
    }

    private function applyOrdersTableFilters($query, Request $request): void
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

        if ($request->filled('filter_profile')) {
            $profile = (string) $request->input('filter_profile');

            if ($profile === '1') {
                $query->where('is_non_profile', false);
            } elseif ($profile === '0') {
                $query->where('is_non_profile', true);
            }
        }

        if ($request->filled('filter_city')) {
            $query->where('city_id', $request->integer('filter_city'));
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

        if ($request->filled('filter_work_type')) {
            $query->where('work_type_id', $request->integer('filter_work_type'));
        }

        if ($request->filled('filter_name')) {
            $query->where('client_name', 'like', '%'.$request->input('filter_name').'%');
        }

        if ($request->filled('filter_phone')) {
            $query->where('client_phone', 'like', '%'.$request->input('filter_phone').'%');
        }

        if ($request->filled('filter_employee')) {
            $query->where('user_id', $request->integer('filter_employee'));
        }
    }

    private function getFilterOptions(User $user): array
    {
        $orders = Order::query()->forPortalUser($user);

        return [
            'cities' => PortalCityOptions::cityMapForOrderFilters($user, $orders),

            'statuses' => self::STATUS_LABELS,

            'types' => self::TYPE_LABELS,

            'sources' => OrderSourceFilter::optionsForOrdersQuery($orders),

            'workTypes' => WorkType::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),

            'employees' => User::query()
                ->whereIn('id', (clone $orders)->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
        ];
    }
}
