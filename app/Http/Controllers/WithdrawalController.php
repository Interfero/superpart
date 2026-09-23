<?php

namespace App\Http\Controllers;

use App\Models\PartnerBankCard;
use App\Models\PortalNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\OrderSourceFilter;
use App\Support\PartnerFinancePool;
use App\Support\UserBalance;
use App\Support\WithdrawableTransactions;
use App\Support\WithdrawalSubmitter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    private const STATUS_LABELS = [
        'in_work'   => 'В работе',
        'completed' => 'Выполнен',
        'rejected'  => 'Отклонено',
    ];

    public function create(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->effectiveOwnerId();

        // Тот же набор, что «Доступно к выводу» на главной (холд 36ч + не в заявке на выплату).
        $query = WithdrawableTransactions::queryFor($user)
            ->with([
                'order.city',
                'order.source',
                'order.referenceSource',
                'user',
            ]);

        $allForOptions = (clone $query)->get();
        $sourceOptions = [];
        foreach ($allForOptions as $tx) {
            $order = $tx->order;
            if (! $order) {
                continue;
            }
            if ($order->reference_source_id) {
                $key = 'r'.$order->reference_source_id;
            } elseif ($order->source_id) {
                $key = 's'.$order->source_id;
            } else {
                continue;
            }
            $name = $order->sourceDisplayName();
            if ($name === '' || $name === '—') {
                continue;
            }
            $sourceOptions[$key] = $name;
        }
        asort($sourceOptions, SORT_NATURAL | SORT_FLAG_CASE);

        if ($request->filled('filter_source')) {
            $filterSource = (string) $request->input('filter_source');
            $query->whereHas('order', function ($orderQuery) use ($filterSource) {
                OrderSourceFilter::apply($orderQuery, $filterSource);
            });
        }

        $transactions = $query->orderByDesc('completed_at')->get();

        // Админ/разработчик: свои реквизиты из настроек + карты партнёров из начислений.
        // Партнёр: только свои.
        $bankCardOwnerIds = ($user->hasElevatedAccess() || PartnerFinancePool::usesPool($user))
            ? $transactions->pluck('user_id')->push($ownerId)->unique()->filter()->values()->all()
            : [$ownerId];

        if ($bankCardOwnerIds === []) {
            $bankCardOwnerIds = [$ownerId];
        }

        $bankCards = PartnerBankCard::query()
            ->whereIn('user_id', $bankCardOwnerIds)
            ->orderBy('id')
            ->get();

        $sourceTotals = [];
        foreach ($allForOptions as $tx) {
            $name = $tx->order?->sourceDisplayName() ?? '—';
            $sourceTotals[$name] = ($sourceTotals[$name] ?? 0) + (float) $tx->amount;
        }
        arsort($sourceTotals);

        return view('withdrawals.form', [
            'mode'         => 'create',
            'withdrawal'   => null,
            'transactions' => $transactions,
            'bankCards'    => $bankCards,
            'balance'      => $user->walletBalance(),
            'available'    => (float) WithdrawableTransactions::queryFor($user)->sum('amount'),
            'sourceOptions' => $sourceOptions,
            'sourceTotals' => $sourceTotals,
            'filterSource' => $request->input('filter_source'),
            'showPartnerColumn' => $user->hasElevatedAccess() || PartnerFinancePool::usesPool($user),
            'batchWithdrawals' => collect(),
            'batchItems' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'requisites'        => 'required_without:bank_card_id|nullable|string|max:1000',
            'bank_card'         => 'nullable|string|max:255',
            'bank_card_id'      => 'nullable|integer|exists:partner_bank_cards,id',
            'comment'           => 'nullable|string|max:1000',
            'transaction_ids'   => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ], [
            'requisites.required_without' => 'Укажите реквизиты для вывода или выберите сохранённую карту',
            'transaction_ids.required' => 'Выберите хотя бы одну заявку',
            'transaction_ids.min'      => 'Выберите хотя бы одну заявку',
        ]);

        $user = auth()->user();
        $ownerId = $user->effectiveOwnerId();
        $transactionIds = array_values(array_unique(array_map('intval', $request->transaction_ids)));

        $partnerIds = Transaction::query()
            ->whereIn('id', $transactionIds)
            ->pluck('user_id')
            ->unique()
            ->filter()
            ->values();

        $bankCard = null;
        if ($request->filled('bank_card_id')) {
            $bankCardQuery = PartnerBankCard::query()->whereKey((int) $request->bank_card_id);

            if ($user->hasElevatedAccess() || PartnerFinancePool::usesPool($user)) {
                // Свои реквизиты или карты партнёров из выбранных начислений.
                $allowedCardOwners = $partnerIds->push($ownerId)->unique()->all();
                $bankCardQuery->whereIn('user_id', $allowedCardOwners);
            } else {
                $bankCardQuery->where('user_id', $ownerId);
            }

            $bankCard = $bankCardQuery->first();

            if (! $bankCard) {
                return back()->withErrors(['bank_card_id' => 'Карта не найдена.'])->withInput();
            }

            if ($bankCard->isCard() && $bankCard->recipient_birth_date === null) {
                return back()->withErrors([
                    'bank_card_id' => 'У выбранной карты не указана дата рождения получателя. Обновите карту в настройках.',
                ])->withInput();
            }
        }

        $requisites = $bankCard
            ? $bankCard->requisitesText()
            : trim((string) $request->requisites);

        if ($requisites === '') {
            return back()->withErrors(['requisites' => 'Укажите реквизиты для вывода.'])->withInput();
        }

        $bankCardLabel = $bankCard
            ? $bankCard->selectLabel()
            : $request->bank_card;

        $recipientBirthDate = $bankCard?->recipient_birth_date;
        $comment = $request->comment;

        try {
            $created = WithdrawalSubmitter::submit(
                $user,
                $transactionIds,
                $requisites,
                $bankCardLabel,
                $recipientBirthDate,
                $comment
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($created->isEmpty()) {
            return back()->withErrors(['transaction_ids' => 'Не найдено подходящих начислений'])->withInput();
        }

        $withdrawal = $created->sortBy('id')->first();
        if ($withdrawal->wasRecentlyCreated) {
            $this->notifyWithdrawalCreated($withdrawal);
        }

        return redirect()
            ->route('withdrawals.show', $withdrawal->id)
            ->with('success', $withdrawal->wasRecentlyCreated
                ? 'Заявка на выплату создана'
                : 'Заявка на выплату уже была создана');
    }

    public function show(int $id)
    {
        $user = auth()->user();

        $query = WithdrawalRequest::query()
            ->with([
                'items.transaction.user',
                'items.city',
                'items.order.source',
                'items.order.referenceSource',
                'user',
            ]);

        if ($user->hasElevatedAccess()) {
            // все
        } elseif (PartnerFinancePool::usesPool($user)) {
            $query->whereIn('user_id', PartnerFinancePool::partnerIds());
        } else {
            $query->where('user_id', $user->id);
        }

        $withdrawal = $query->findOrFail($id);

        $ownerCount = $withdrawal->items
            ->map(fn ($item) => (int) ($item->transaction?->user_id ?? 0))
            ->filter()
            ->unique()
            ->count();

        return view('withdrawals.form', [
            'mode'         => 'show',
            'withdrawal'   => $withdrawal,
            'transactions' => null,
            'bankCards'    => collect(),
            'balance'      => $user->walletBalance(),
            'showPartnerColumn' => $ownerCount > 1 || $user->hasElevatedAccess(),
            'batchWithdrawals' => collect([$withdrawal]),
            'batchItems' => $withdrawal->items,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $user = auth()->user();

        if (! $user->hasElevatedAccess()) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:in_work,completed,rejected'],
            'planned_collect_at' => ['nullable', 'date'],
        ]);

        $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);

        $oldStatus = $withdrawal->status;

        $withdrawal->status = $data['status'];
        $withdrawal->planned_collect_at = $data['planned_collect_at'] ?? null;

        if ($withdrawal->isDirty('planned_collect_at')) {
            $withdrawal->collect_reminded_at = null;
        }

        $withdrawal->save();

        if ($oldStatus !== $withdrawal->status) {
            if ($withdrawal->status === 'completed') {
                // FR-FIN-06: до списания ещё раз проверить, что все позиции всё ещё SP-допустимы.
                $ineligible = $this->findIneligibleWithdrawalOrderIds($withdrawal);
                if ($ineligible !== []) {
                    $withdrawal->status = $oldStatus;
                    $withdrawal->save();

                    return back()->withErrors([
                        'status' => 'Нельзя завершить выплату: заявки без SP-источника — '.implode(', ', $ineligible),
                    ]);
                }

                try {
                    UserBalance::applyWithdrawal($withdrawal);
                } catch (\Throwable $e) {
                    Log::error('Withdrawal ledger apply failed', [
                        'withdrawal_id' => $withdrawal->id,
                        'error' => $e->getMessage(),
                    ]);

                    return back()->withErrors([
                        'status' => 'Статус обновлён, но списание с баланса не выполнено: '.$e->getMessage(),
                    ]);
                }
            }

            $this->notifyWithdrawalStatusChanged($withdrawal, $oldStatus, $withdrawal->status);
        }

        return back()->with('success', 'Данные выплаты обновлены');
    }

    /**
     * @return list<int>
     */
    private function findIneligibleWithdrawalOrderIds(WithdrawalRequest $withdrawal): array
    {
        $withdrawal->loadMissing(['items.transaction.order.referenceSource']);

        $bad = [];
        foreach ($withdrawal->items as $item) {
            $order = $item->transaction?->order;
            if (! $order) {
                continue;
            }
            $ref = $order->referenceSource;
            // Допустимость только по reference_source.available_for_superpart (ТЗ FR-SRC-01).
            $ok = $ref && (bool) $ref->available_for_superpart;
            if (! $ok) {
                $bad[] = (int) $order->id;
            }
        }

        return array_values(array_unique($bad));
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = WithdrawalRequest::query();

        if ($user->hasElevatedAccess()) {
            // все
        } elseif (PartnerFinancePool::usesPool($user)) {
            $query->whereIn('user_id', PartnerFinancePool::partnerIds());
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($inner) use ($search) {
                $inner->where('id', 'like', '%'.$search.'%');
                if (Schema::hasColumn('withdrawal_requests', 'batch_id')) {
                    $inner->orWhereIn('batch_id', function ($sub) use ($search) {
                        $sub->select('batch_id')
                            ->from('withdrawal_requests as wr_search')
                            ->where('wr_search.id', 'like', '%'.$search.'%')
                            ->whereNotNull('wr_search.batch_id');
                    });
                }
            });
        }

        if ($request->filled('filter_status') && $request->filter_status !== 'all') {
            $query->where('status', $request->filter_status);
        }

        $statusParam = $request->input('status', 'all');

        $baseQuery = WithdrawalRequest::query();

        if ($user->hasElevatedAccess()) {
            // все
        } elseif (PartnerFinancePool::usesPool($user)) {
            $baseQuery->whereIn('user_id', PartnerFinancePool::partnerIds());
        } else {
            $baseQuery->where('user_id', $user->id);
        }

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $baseQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $countAll = $this->countWithdrawalGroups((clone $baseQuery));
        $countInWork = $this->countWithdrawalGroups((clone $baseQuery)->where('status', 'in_work'));
        $countCompleted = $this->countWithdrawalGroups((clone $baseQuery)->where('status', 'completed'));

        if ($statusParam !== 'all') {
            $query->where('status', $statusParam);
        }

        $withdrawals = $this->paginateWithdrawalGroups($query);

        return view('withdrawals.index', [
            'withdrawals'     => $withdrawals,
            'balance'         => $user->walletBalance(),
            'dateFrom'        => $request->date_from,
            'dateTo'          => $request->date_to,
            'search'          => $request->search,
            'filterStatus'    => $request->filter_status,
            'activeStatus'    => $statusParam,
            'countAll'        => $countAll,
            'countInWork'     => $countInWork,
            'countCompleted'  => $countCompleted,
            'statusLabels'    => self::STATUS_LABELS,
        ]);
    }

    private function countWithdrawalGroups($query): int
    {
        if (! Schema::hasColumn('withdrawal_requests', 'batch_id')) {
            return (int) $query->count();
        }

        return (int) $query
            ->toBase()
            ->reorder()
            ->selectRaw('COUNT(DISTINCT COALESCE(batch_id, CONCAT("id-", id))) as aggregate_count')
            ->value('aggregate_count');
    }

    private function paginateWithdrawalGroups($query)
    {
        if (! Schema::hasColumn('withdrawal_requests', 'batch_id')) {
            return $query->orderByDesc('created_at')->paginate(25)->withQueryString();
        }

        $grouped = (clone $query)
            ->selectRaw('COALESCE(batch_id, CONCAT("id-", id)) as grp')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('MAX(id) as max_id')
            ->selectRaw('MIN(created_at) as created_at')
            ->selectRaw('SUM(total_amount) as total_amount')
            ->selectRaw('COUNT(*) as batch_size')
            ->selectRaw('MAX(status) as status')
            ->groupByRaw('COALESCE(batch_id, CONCAT("id-", id))')
            ->orderByDesc(DB::raw('MIN(created_at)'))
            ->paginate(25)
            ->withQueryString();

        $ids = collect($grouped->items())->pluck('id')->filter()->all();
        $models = WithdrawalRequest::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $grouped->setCollection(
            collect($grouped->items())->map(function ($row) use ($models) {
                $model = $models->get((int) $row->id) ?? new WithdrawalRequest();
                $model->id = (int) $row->id;
                $model->created_at = \Carbon\Carbon::parse($row->created_at);
                $model->status = (string) $row->status;
                $model->setAttribute('total_amount', $row->total_amount);
                $model->setAttribute('index_batch_size', (int) $row->batch_size);
                $model->setAttribute('index_id_label', ((int) $row->batch_size) > 1
                    ? ((int) $row->id).'–'.((int) $row->max_id)
                    : (string) $row->id);

                return $model;
            })
        );

        return $grouped;
    }

    private function notifyWithdrawalCreated(WithdrawalRequest $withdrawal): void
    {
        try {
            $withdrawal->load('user');

            $recipients = collect();

            if ($withdrawal->user) {
                $recipients->push($withdrawal->user);
            }

            User::query()
                ->portalAdmins()
                ->get()
                ->each(fn (User $admin) => $recipients->push($admin));

            $recipients
                ->unique('id')
                ->each(function (User $recipient) use ($withdrawal) {
                    PortalNotification::create([
                        'user_id' => $recipient->id,
                        'type' => 'withdrawal_created',
                        'title' => 'Заявка на выплату',
                        'message' => 'Создана заявка на выплату #' . $withdrawal->id . ' на сумму ' . number_format((float) $withdrawal->total_amount, 0, ',', ' ') . ' ₽.',
                        'url' => route('withdrawals.show', $withdrawal->id),
                    ]);
                });
        } catch (\Throwable) {
            // Уведомления не должны ломать создание заявки на выплату.
        }
    }

    private function notifyWithdrawalStatusChanged(WithdrawalRequest $withdrawal, ?string $oldStatus, string $newStatus): void
    {
        try {
            $withdrawal->load('user');

            $statusLabels = [
                'in_work' => 'На рассмотрении',
                'completed' => 'Выполнена',
                'rejected' => 'Отклонена',
            ];

            $oldLabel = $statusLabels[$oldStatus] ?? ($oldStatus ?: '—');
            $newLabel = $statusLabels[$newStatus] ?? $newStatus;

            if ($withdrawal->user) {
                PortalNotification::create([
                    'user_id' => $withdrawal->user_id,
                    'type' => 'withdrawal_status_changed',
                    'title' => 'Изменён статус выплаты',
                    'message' => 'Статус заявки на вывод #' . $withdrawal->id . ' изменён: ' . $oldLabel . ' → ' . $newLabel . '.',
                    'url' => route('withdrawals.show', $withdrawal->id),
                ]);
            }
        } catch (\Throwable) {
            // Уведомления не должны ломать изменение статуса выплаты.
        }
    }
}