<?php

namespace App\Services;

use App\Models\City;
use App\Models\Order;
use App\Models\ReferenceSource;
use App\Models\User;
use App\Models\WithdrawalRequestItem;
use App\Support\CrmOrderStatusMapper;
use App\Support\OrderCrmIdSync;
use App\Support\OrderSnapshotChecksum;
use App\Support\PartnerChargeCalculator;
use App\Support\UserBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Применение order.snapshot.changed из CRM (ТЗ FR-SYNC-06).
 */
class OrderSnapshotApplier
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result: string, http: int, message?: string, charged?: bool, duplicate?: bool, stale?: bool}
     */
    public function apply(array $payload): array
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        $eventType = (string) ($payload['event_type'] ?? '');
        $eventVersion = (int) ($payload['event_version'] ?? 0);
        $checksum = (string) ($payload['checksum'] ?? '');
        $orderBlock = $payload['order'] ?? null;

        if ($eventId === '' || $eventVersion < 1 || ! is_array($orderBlock)) {
            return ['ok' => false, 'result' => 'rejected', 'http' => 422, 'message' => 'Некорректный snapshot'];
        }

        if ($eventType !== '' && $eventType !== 'order.snapshot.changed') {
            return ['ok' => false, 'result' => 'rejected', 'http' => 422, 'message' => 'Неизвестный event_type'];
        }

        $computed = OrderSnapshotChecksum::compute($orderBlock, $eventVersion);
        if ($checksum === '' || ! hash_equals($computed, $checksum)) {
            Log::warning('OrderSnapshotApplier: checksum mismatch', [
                'event_id' => $eventId,
                'order_id' => $orderBlock['id'] ?? null,
            ]);

            return ['ok' => false, 'result' => 'rejected', 'http' => 422, 'message' => 'checksum mismatch'];
        }

        return DB::transaction(function () use ($payload, $eventId, $eventVersion, $checksum, $orderBlock, $eventType) {
            $existingInbox = DB::table('sync_inbox')->where('event_id', $eventId)->lockForUpdate()->first();
            if ($existingInbox && in_array((string) $existingInbox->result, ['applied', 'duplicate', 'stale'], true)) {
                return [
                    'ok' => true,
                    'result' => 'duplicate',
                    'http' => 200,
                    'duplicate' => true,
                ];
            }

            $crmOrderId = (int) ($orderBlock['id'] ?? 0);
            if ($crmOrderId < 1) {
                $this->upsertInbox($eventId, null, $eventVersion, $checksum, $eventType, 'rejected', 'invalid_order_id', $payload);

                return ['ok' => false, 'result' => 'rejected', 'http' => 422, 'message' => 'order.id required'];
            }

            /** @var Order|null $order */
            $order = Order::query()->whereCrmRecord($crmOrderId)->lockForUpdate()->first();
            $order = $this->vacateUnsyncedLocalOrder($order, $crmOrderId, $orderBlock);

            if ($order && (int) ($order->crm_sync_version ?? 0) >= $eventVersion) {
                $this->upsertInbox($eventId, $crmOrderId, $eventVersion, $checksum, $eventType, 'stale', null, $payload, now());

                return [
                    'ok' => true,
                    'result' => 'stale',
                    'http' => 200,
                    'stale' => true,
                ];
            }

            try {
                $charged = $this->applySnapshotToOrder($order, $orderBlock, $eventVersion, $checksum);
            } catch (\InvalidArgumentException $e) {
                $this->upsertInbox($eventId, $crmOrderId, $eventVersion, $checksum, $eventType, 'rejected', $e->getMessage(), $payload);

                return ['ok' => false, 'result' => 'rejected', 'http' => 422, 'message' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('OrderSnapshotApplier: apply failed', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
                $this->upsertInbox($eventId, $crmOrderId, $eventVersion, $checksum, $eventType, 'rejected', 'apply_failed', $payload);

                return ['ok' => false, 'result' => 'rejected', 'http' => 500, 'message' => 'Ошибка применения'];
            }

            $this->upsertInbox($eventId, $crmOrderId, $eventVersion, $checksum, $eventType, 'applied', null, $payload, now());

            return [
                'ok' => true,
                'result' => 'applied',
                'http' => 200,
                'charged' => $charged,
            ];
        });
    }

    /**
     * Если на этом ID лежит не ушедшая в CRM заявка портала — увести её в сторону.
     *
     * @param  array<string, mixed>  $orderBlock
     */
    private function vacateUnsyncedLocalOrder(?Order $order, int $crmOrderId, array $orderBlock): ?Order
    {
        unset($orderBlock);

        $occupant = Order::query()->whereKey($crmOrderId)->lockForUpdate()->first();

        if ($occupant && OrderCrmIdSync::isUnsyncedLocal($occupant)) {
            $fromId = (int) $occupant->id;
            $freeId = OrderCrmIdSync::nextParkedId();
            Log::warning('OrderSnapshotApplier: relocating unsynced local order to avoid CRM id clash', [
                'from_id' => $fromId,
                'to_id' => $freeId,
                'crm_id' => $crmOrderId,
            ]);
            OrderCrmIdSync::relocate($occupant, $freeId);

            if ($order && (int) $order->id === $fromId) {
                $order = null;
            }
        } elseif (! $order && $occupant) {
            $order = $occupant;
        }

        if ($order && OrderCrmIdSync::isUnsyncedLocal($order)) {
            $freeId = OrderCrmIdSync::nextParkedId();
            Log::warning('OrderSnapshotApplier: relocating unsynced CRM-lookup row', [
                'from_id' => (int) $order->id,
                'to_id' => $freeId,
                'crm_id' => $crmOrderId,
            ]);
            OrderCrmIdSync::relocate($order, $freeId);

            return null;
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $orderBlock
     */
    private function applySnapshotToOrder(?Order $order, array $orderBlock, int $eventVersion, string $checksum): bool
    {
        $crmOrderId = (int) $orderBlock['id'];
        $partnerUserId = isset($orderBlock['partner_user_id']) ? (int) $orderBlock['partner_user_id'] : 0;
        $spAvailable = (bool) ($orderBlock['source_available_for_superpart'] ?? false);
        $orderTypeCrm = (string) ($orderBlock['order_type'] ?? 'new');
        $typeMap = [
            'new' => 'first_time',
            'repeat' => 'repeat',
            'warranty' => 'warranty',
        ];
        $type = $typeMap[$orderTypeCrm] ?? 'first_time';
        $isNonProfile = (($orderBlock['order_core'] ?? '') === 'non_core');

        $crmStatus = (string) ($orderBlock['status'] ?? '');
        $shouldCharge = (bool) ($orderBlock['should_charge'] ?? false);
        $rewardAmount = (int) ($orderBlock['reward_amount'] ?? 0);
        $rewardRule = (string) ($orderBlock['reward_rule'] ?? '');
        $crmSourceId = ! empty($orderBlock['source_id']) ? (int) $orderBlock['source_id'] : null;
        $previousSourceId = $order ? (int) ($order->crm_source_id ?? 0) : 0;
        $wasEligible = $order ? (bool) ($order->is_superpart_eligible ?? true) && $order->excluded_at === null : true;

        // Независимо пересчитать reward (Phase D расширит computer 40%).
        $paid = (int) ($orderBlock['amount_paid'] ?? 0);
        $parts = (int) ($orderBlock['amount_parts'] ?? $orderBlock['amount_comp'] ?? 0);
        $createdAt = $orderBlock['created_at'] ?? null;
        $equipment = isset($orderBlock['equipment_type']) ? (string) $orderBlock['equipment_type'] : null;
        $localReward = (int) PartnerChargeCalculator::fromPaidAndParts($paid, $parts, $equipment, $createdAt);
        if ($rewardAmount > 0 && $localReward !== $rewardAmount) {
            Log::warning('OrderSnapshotApplier: reward mismatch — charge blocked', [
                'order_id' => $crmOrderId,
                'crm' => $rewardAmount,
                'sp' => $localReward,
                'rule' => $rewardRule,
            ]);
            $shouldCharge = false;
        }
        $charge = $shouldCharge ? min($localReward, 2500) : 0;

        if ($crmStatus === 'completed' && $shouldCharge && $charge > 0) {
            $portalStatus = 'waiting_payment';
        } elseif ($crmStatus === 'completed') {
            $portalStatus = CrmOrderStatusMapper::fromCrmCompleted($charge > 0 ? (float) $charge : null, $orderTypeCrm);
        } else {
            $portalStatus = CrmOrderStatusMapper::toPortal($crmStatus, $isNonProfile);
            if ($portalStatus === null) {
                throw new \InvalidArgumentException('unknown_status:'.$crmStatus);
            }
        }

        $referenceSourceId = null;
        if (! empty($orderBlock['source_id'])) {
            $name = isset($orderBlock['marketing_source_name']) && is_string($orderBlock['marketing_source_name'])
                ? $orderBlock['marketing_source_name']
                : null;
            $referenceSourceId = ReferenceSource::ensureFromCrmSource((int) $orderBlock['source_id'], $name)->id;
        }

        if (! $order) {
            if ($partnerUserId < 1 || ! User::query()->whereKey($partnerUserId)->exists()) {
                throw new \InvalidArgumentException('partner_not_found');
            }
            if (empty($orderBlock['city_id'])) {
                throw new \InvalidArgumentException('city_required');
            }

            $city = City::query()->where('levelion_city_id', (int) $orderBlock['city_id'])->first();
            if (! $city) {
                $city = City::create([
                    'name' => 'Город CRM #'.$orderBlock['city_id'],
                    'levelion_city_id' => (int) $orderBlock['city_id'],
                    'is_available' => true,
                    'load_percentage' => 0,
                ]);
            }

            $street = (string) ($orderBlock['street'] ?? '—');
            $house = trim((string) ($orderBlock['house'] ?? ''));
            if ($house === '') {
                $house = 'б/н';
            }
            $flat = $orderBlock['flat'] ?? null;
            $addressLine = trim(implode(' ', array_filter([
                $street,
                $house !== 'б/н' ? 'д. '.$house : '',
                $flat !== null && $flat !== '' ? 'кв. '.$flat : '',
            ])));

            $dt = isset($orderBlock['datetime_order'])
                ? Carbon::parse($orderBlock['datetime_order'])
                : now();

            $order = new Order([
                'user_id' => $partnerUserId,
                'city_id' => $city->id,
                'status' => $portalStatus === 'waiting_payment' ? 'not_processed' : $portalStatus,
                'type' => $type,
                'source_id' => null,
                'reference_source_id' => $referenceSourceId,
                'work_type_id' => null,
                'order_time' => $dt,
                'client_name' => (string) ($orderBlock['client_name'] ?? 'Клиент'),
                'client_phone' => (string) ($orderBlock['client_phone'] ?? '0000000000'),
                'is_non_profile' => $isNonProfile,
                'settlement' => null,
                'address' => $addressLine !== '' ? $addressLine : null,
                'street' => $street,
                'house' => $house,
                'flat' => $flat,
                'address_adds' => $orderBlock['address_adds'] ?? null,
                'order_adds' => $orderBlock['order_adds'] ?? null,
                'employee_id' => null,
                'charge_amount' => null,
                'created_local' => isset($orderBlock['created_at']) ? Carbon::parse($orderBlock['created_at']) : now(),
                'closed_local' => null,
                'sync_status' => 'synced',
                'equipment_type' => $orderBlock['equipment_type'] ?? null,
                'order_core' => $orderBlock['order_core'] ?? null,
            ]);
            $order->id = $crmOrderId;
            $order->levelion_order_id = $crmOrderId;
            $order->crm_sync_version = $eventVersion;
            $order->crm_checksum = $checksum;
            $this->applyEligibilityFields($order, $spAvailable, $crmSourceId, $shouldCharge ? $charge : 0);
            $order->save();
        } else {
            $updates = [
                'status' => $portalStatus,
                'type' => $type,
                'is_non_profile' => $isNonProfile,
                'equipment_type' => $orderBlock['equipment_type'] ?? $order->equipment_type,
                'order_core' => $orderBlock['order_core'] ?? $order->order_core,
                'order_adds' => $orderBlock['order_adds'] ?? $order->order_adds,
                'crm_sync_version' => $eventVersion,
                'crm_checksum' => $checksum,
                'sync_status' => 'synced',
                'levelion_order_id' => $crmOrderId,
            ];
            if ($referenceSourceId) {
                $updates['reference_source_id'] = $referenceSourceId;
                $updates['source_id'] = null;
            }
            if ($referenceSourceId && ! $spAvailable) {
                ReferenceSource::query()->whereKey($referenceSourceId)->update(['available_for_superpart' => false]);
            }
            if (! $spAvailable) {
                $updates['status'] = $portalStatus;
            }
            if ($partnerUserId > 0 && (int) $order->user_id !== $partnerUserId && User::query()->whereKey($partnerUserId)->exists()) {
                // Не меняем кошелёк задним числом без отдельного решения — только если заявка новая без charge.
                if ((float) ($order->charge_amount ?? 0) <= 0) {
                    $updates['user_id'] = $partnerUserId;
                }
            }
            $order->fill($updates);
            $this->applyEligibilityFields($order, $spAvailable, $crmSourceId, $shouldCharge ? $charge : 0);
            $order->save();
        }

        if ($previousSourceId !== (int) ($crmSourceId ?? 0) || $wasEligible !== $spAvailable) {
            $this->recordSourceHistory(
                (int) $order->id,
                $previousSourceId > 0 ? $previousSourceId : null,
                $crmSourceId,
                $wasEligible,
                $spAvailable,
                $spAvailable ? ($wasEligible ? 'source_changed' : 'returned_to_sp') : 'left_sp',
                $eventVersion
            );
        }

        if (! $spAvailable) {
            $this->stornoUnpaidChargeIfNeeded($order->fresh());

            return false;
        }

        $charged = false;
        if ($shouldCharge && $charge > 0 && $type !== 'warranty' && $spAvailable) {
            $already = DB::table('transactions')
                ->where('order_id', $order->id)
                ->where('operation_type', 'charge')
                ->lockForUpdate()
                ->exists();

            if (! $already) {
                $user = User::query()->lockForUpdate()->findOrFail((int) $order->user_id);
                $closedAt = isset($orderBlock['closed_at']) && $orderBlock['closed_at']
                    ? Carbon::parse($orderBlock['closed_at'])
                    : now();
                UserBalance::applyCharge($user, (int) $order->id, (float) $charge, $closedAt);
                $order->status = 'waiting_payment';
                $order->charge_amount = $charge;
                if (Schema::hasColumn('orders', 'expected_reward')) {
                    $order->expected_reward = (int) $charge;
                }
                $order->closed_local = $closedAt;
                $order->save();
                $charged = true;
            } else {
                // FR-FIN-04: уже есть charge — сверка ожидаемой суммы; correction если не выплачено.
                $this->reconcileUnpaidChargeDelta($order, (float) $charge, $orderBlock, $eventVersion);

                if ($order->closed_local === null) {
                    $order->closed_local = isset($orderBlock['closed_at']) && $orderBlock['closed_at']
                        ? Carbon::parse($orderBlock['closed_at'])
                        : now();
                    if ($order->status !== 'waiting_payment') {
                        $order->status = 'waiting_payment';
                    }
                    $order->save();
                }
            }
        } elseif ($type === 'warranty') {
            $order->status = 'warranty';
            $order->charge_amount = null;
            $order->closed_local = isset($orderBlock['closed_at']) && $orderBlock['closed_at']
                ? Carbon::parse($orderBlock['closed_at'])
                : ($order->closed_local ?? now());
            $order->save();
        }

        return $charged;
    }

    /**
     * FR-FIN-04: delta correction для невыплаченного начисления; выплаченное — только audit-log.
     *
     * @param  array<string, mixed>  $orderBlock
     */
    private function reconcileUnpaidChargeDelta(Order $order, float $expectedCharge, array $orderBlock, int $eventVersion): void
    {
        $ledger = (float) DB::table('transactions')
            ->where('order_id', $order->id)
            ->whereIn('operation_type', ['charge', 'correction'])
            ->sum('amount');

        $delta = round($expectedCharge - $ledger);
        if ($delta === 0.0) {
            return;
        }

        $chargeTx = DB::table('transactions')
            ->where('order_id', $order->id)
            ->where('operation_type', 'charge')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $chargeTxIds = DB::table('transactions')
            ->where('order_id', $order->id)
            ->where('operation_type', 'charge')
            ->pluck('id');

        $withdrawn = false;
        if ($chargeTxIds->isNotEmpty()) {
            $withdrawn = WithdrawalRequestItem::query()
                ->whereIn('transaction_id', $chargeTxIds)
                ->whereHas('withdrawalRequest', fn ($q) => $q->where('status', 'completed'))
                ->exists();
        }

        if ($withdrawn) {
            Log::warning('OrderSnapshotApplier: FIN-04 financial_discrepancy (paid out) — no auto correction', [
                'order_id' => $order->id,
                'ledger' => $ledger,
                'expected' => $expectedCharge,
                'delta' => $delta,
                'event_version' => $eventVersion,
            ]);
            $this->recordFinancialDiscrepancy(
                (int) $order->id,
                'paid_out_amount_mismatch',
                (int) round($ledger),
                (int) round($expectedCharge),
                (int) $delta,
                $eventVersion
            );

            return;
        }

        $user = User::query()->lockForUpdate()->find((int) $order->user_id);
        if (! $user) {
            return;
        }

        $rule = (string) ($orderBlock['reward_rule'] ?? PartnerChargeCalculator::RULE_DEFAULT);
        $idempotencyKey = sprintf('fin04:order:%d:v%d:delta:%d', (int) $order->id, $eventVersion, (int) $delta);

        UserBalance::applyCorrection($user, (int) $order->id, $delta, now(), [
            'related_transaction_id' => $chargeTx->id ?? null,
            'reason_code' => 'amount_changed',
            'idempotency_key' => $idempotencyKey,
            'reward_rule' => $rule,
        ]);

        Log::info('OrderSnapshotApplier: FIN-04 correction applied', [
            'order_id' => $order->id,
            'delta' => $delta,
            'event_version' => $eventVersion,
        ]);
    }

    /** ТЗ FR-SRC-02: заявка больше не SP — скрыть и сторнировать невыплаченное. */
    public function applyLeaveSuperpart(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->applyEligibilityFields($order, false, $order->crm_source_id ? (int) $order->crm_source_id : null, 0);
            $order->save();
            $this->stornoUnpaidChargeIfNeeded($order->fresh() ?? $order);
        });
    }

    /**
     * FR-SRC / сторно: невыплаченное начисление уходит с баланса при потере SP-источника.
     * Завершённые выплаты не трогаем.
     */
    private function stornoUnpaidChargeIfNeeded(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $ledger = (float) DB::table('transactions')
            ->where('order_id', $order->id)
            ->whereIn('operation_type', ['charge', 'correction'])
            ->sum('amount');

        if ($ledger <= 0) {
            return;
        }

        $chargeTxIds = DB::table('transactions')
            ->where('order_id', $order->id)
            ->where('operation_type', 'charge')
            ->pluck('id');

        $withdrawn = false;
        if ($chargeTxIds->isNotEmpty()) {
            $withdrawn = WithdrawalRequestItem::query()
                ->whereIn('transaction_id', $chargeTxIds)
                ->whereHas('withdrawalRequest', fn ($q) => $q->where('status', 'completed'))
                ->exists();
        }

        if ($withdrawn) {
            Log::info('OrderSnapshotApplier: leave-SP but charge withdrawn — skip storno', [
                'order_id' => $order->id,
                'ledger' => $ledger,
            ]);
            $this->recordFinancialDiscrepancy(
                (int) $order->id,
                'source_changed_after_payout',
                (int) round($ledger),
                0,
                0,
                (int) ($order->crm_sync_version ?? 0)
            );

            return;
        }

        // Убрать позиции из незавершённых заявок на выплату (FR-FIN-05).
        if ($chargeTxIds->isNotEmpty()) {
            $pendingItems = WithdrawalRequestItem::query()
                ->whereIn('transaction_id', $chargeTxIds)
                ->whereHas('withdrawalRequest', fn ($q) => $q->whereNotIn('status', ['completed']))
                ->with('withdrawalRequest')
                ->get();

            foreach ($pendingItems as $item) {
                $wd = $item->withdrawalRequest;
                $item->delete();
                if ($wd) {
                    $remaining = (float) DB::table('withdrawal_request_items as i')
                        ->join('transactions as t', 't.id', '=', 'i.transaction_id')
                        ->where('i.withdrawal_request_id', $wd->id)
                        ->sum('t.amount');
                    $wd->total_amount = max(0, round($remaining));
                    if ($wd->items()->count() === 0) {
                        $wd->status = 'cancelled';
                        $wd->total_amount = 0;
                    }
                    $wd->save();
                }
            }
        }

        $user = User::query()->lockForUpdate()->find((int) $order->user_id);
        if (! $user) {
            return;
        }

        $chargeTx = DB::table('transactions')
            ->where('order_id', $order->id)
            ->where('operation_type', 'charge')
            ->orderBy('id')
            ->first();

        UserBalance::applyCorrection($user, (int) $order->id, -1 * round($ledger), now(), [
            'related_transaction_id' => $chargeTx->id ?? null,
            'reason_code' => 'leave_sp_source',
            'idempotency_key' => sprintf('leave-sp:order:%d:ledger:%d', (int) $order->id, (int) round($ledger)),
            'reward_rule' => null,
        ]);
        if (Schema::hasColumn('orders', 'expected_reward')) {
            $order->expected_reward = 0;
            $order->charge_amount = 0;
            $order->save();
        }

        Log::info('OrderSnapshotApplier: leave-SP storno applied', [
            'order_id' => $order->id,
            'storno' => -1 * round($ledger),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertInbox(
        string $eventId,
        ?int $orderId,
        int $eventVersion,
        string $checksum,
        string $eventType,
        string $result,
        ?string $errorCode,
        array $payload,
        $appliedAt = null,
    ): void {
        $row = [
            'order_id' => $orderId,
            'event_version' => $eventVersion,
            'checksum' => $checksum,
            'event_type' => $eventType !== '' ? $eventType : 'order.snapshot.changed',
            'result' => $result,
            'error_code' => $errorCode,
            'payload' => json_encode($this->sanitizeInboxPayload($payload), JSON_UNESCAPED_UNICODE),
            'applied_at' => $appliedAt,
        ];

        $existing = DB::table('sync_inbox')->where('event_id', $eventId)->first();
        $attempts = $existing ? (int) $existing->attempts + 1 : 1;
        if ($existing) {
            $row['attempts'] = $attempts;
            DB::table('sync_inbox')->where('id', $existing->id)->update($row);
        } else {
            $row['event_id'] = $eventId;
            $row['attempts'] = $attempts;
            $row['received_at'] = now();
            DB::table('sync_inbox')->insert($row);
        }

        if ($result === 'rejected' && $attempts >= 5 && Schema::hasTable('sync_dead_letter')) {
            $existsDlq = DB::table('sync_dead_letter')->where('event_id', $eventId)->exists();
            if (! $existsDlq) {
                DB::table('sync_dead_letter')->insert([
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'error_code' => $errorCode,
                    'attempts' => $attempts,
                    'created_at' => now(),
                ]);
                Log::warning('OrderSnapshotApplier: event moved to dead-letter', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'error_code' => $errorCode,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeInboxPayload(array $payload): array
    {
        $order = $payload['order'] ?? null;
        if (is_array($order)) {
            unset(
                $order['client_name'],
                $order['client_phone'],
                $order['street'],
                $order['house'],
                $order['flat'],
                $order['address_adds'],
                $order['order_adds']
            );
            $payload['order'] = $order;
        }

        return $payload;
    }

    private function applyEligibilityFields(Order $order, bool $spAvailable, ?int $crmSourceId, int $expectedReward): void
    {
        if (Schema::hasColumn('orders', 'is_superpart_eligible')) {
            $order->is_superpart_eligible = $spAvailable;
        }
        if (Schema::hasColumn('orders', 'excluded_at')) {
            $order->excluded_at = $spAvailable ? null : ($order->excluded_at ?? now());
        }
        if (Schema::hasColumn('orders', 'exclusion_reason')) {
            $order->exclusion_reason = $spAvailable ? null : 'source_left_superpart';
        }
        if (Schema::hasColumn('orders', 'crm_source_id')) {
            $order->crm_source_id = $crmSourceId;
        }
        if (Schema::hasColumn('orders', 'expected_reward')) {
            $order->expected_reward = $spAvailable ? $expectedReward : 0;
        }
    }

    private function recordSourceHistory(
        int $orderId,
        ?int $fromSourceId,
        ?int $toSourceId,
        bool $fromEligible,
        bool $toEligible,
        string $reason,
        int $eventVersion,
    ): void {
        if (! Schema::hasTable('order_source_history')) {
            return;
        }

        DB::table('order_source_history')->insert([
            'order_id' => $orderId,
            'from_source_id' => $fromSourceId,
            'to_source_id' => $toSourceId,
            'from_eligible' => $fromEligible,
            'to_eligible' => $toEligible,
            'reason' => $reason,
            'event_version' => $eventVersion,
            'created_at' => now(),
        ]);
    }

    private function recordFinancialDiscrepancy(
        int $orderId,
        string $kind,
        int $ledger,
        int $expected,
        int $delta,
        int $eventVersion,
    ): void {
        if (! Schema::hasTable('financial_discrepancies')) {
            return;
        }

        $exists = DB::table('financial_discrepancies')
            ->where('order_id', $orderId)
            ->where('kind', $kind)
            ->where('event_version', $eventVersion)
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('financial_discrepancies')->insert([
            'order_id' => $orderId,
            'kind' => $kind,
            'ledger_amount' => $ledger,
            'expected_amount' => $expected,
            'delta' => $delta,
            'event_version' => $eventVersion,
            'status' => 'open',
            'created_at' => now(),
        ]);
    }
}
