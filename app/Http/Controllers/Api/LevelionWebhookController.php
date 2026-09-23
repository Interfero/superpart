<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Order;
use App\Models\ReferenceSource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrderSnapshotApplier;
use App\Support\OrderCrmIdSync;
use App\Support\UserBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LevelionWebhookController extends Controller
{
    /**
     * Единый снимок заявки из CRM outbox (ТЗ FR-SYNC-03/06).
     */
    public function orderSnapshot(Request $request, OrderSnapshotApplier $applier): JsonResponse
    {
        $payload = $request->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Пустой payload'], 422);
        }

        $result = $applier->apply($payload);

        $body = [
            'ok' => $result['ok'],
            'result' => $result['result'],
            'correlation_id' => $request->attributes->get('correlation_id'),
        ];
        if (! empty($result['duplicate'])) {
            $body['duplicate'] = true;
        }
        if (! empty($result['stale'])) {
            $body['stale'] = true;
        }
        if (array_key_exists('charged', $result)) {
            $body['charged'] = $result['charged'];
        }
        if (! empty($result['message'])) {
            $body['message'] = $result['message'];
        }

        return response()->json($body, $result['http']);
    }

    public function orderCompleted(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'partner_user_id' => ['nullable', 'integer', 'min:1'],
            'charge_amount' => ['required', 'numeric', 'min:0'],
            'partner_reward_base' => ['nullable', 'numeric'],
            'amount_paid' => ['nullable', 'integer'],
            'amount_comp' => ['nullable', 'integer'],
            'order_status' => ['nullable', 'string', 'max:64'],
            'completed_at' => ['nullable', 'string', 'max:40'],
        ]);

        $order = Order::query()
            ->whereCrmRecord((int) $data['order_id'])
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        // Кошелёк всегда = владелец заявки в SuperPart (CRM partner_user_id может быть пустым).
        $walletUserId = (int) $order->user_id;
        $crmPartnerId = (int) ($data['partner_user_id'] ?? 0);
        if ($crmPartnerId > 0 && $crmPartnerId === $walletUserId) {
            $walletUserId = $crmPartnerId;
        }

        // Гарантия: закрываем без начислений.
        if ($order->type === 'warranty') {
            $order->status = 'warranty';
            $order->charge_amount = null;
            $order->closed_local = isset($data['completed_at'])
                ? Carbon::parse($data['completed_at'])
                : now();
            $order->save();

            return response()->json(['ok' => true, 'warranty' => true, 'charged' => false]);
        }

        // Итоговая сумма приходит из CRM; потолок дублирует бизнес-правило (не на фронте).
        $charge = min(round((float) $data['charge_amount']), 2500.0);

        $already = Transaction::query()
            ->where('order_id', $order->id)
            ->where('operation_type', 'charge')
            ->exists();

        if ($already) {
            // Старые бэкапы могли начислить без closed_local — догон даты закрытия.
            if ($order->closed_local === null) {
                $order->closed_local = isset($data['completed_at'])
                    ? Carbon::parse($data['completed_at'])
                    : (Transaction::query()
                        ->where('order_id', $order->id)
                        ->where('operation_type', 'charge')
                        ->value('completed_at') ?? now());
                if ($order->status !== 'waiting_payment' && (float) ($order->charge_amount ?? 0) > 0) {
                    $order->status = 'waiting_payment';
                }
                $order->save();
            }
            UserBalance::sync($walletUserId);

            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $user = User::query()->findOrFail($walletUserId);

        DB::transaction(function () use ($order, $charge, $data, $user) {
            UserBalance::applyCharge(
                $user,
                (int) $order->id,
                $charge,
                isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : now()
            );

            $order->status = 'waiting_payment';
            $order->charge_amount = $charge;
            $order->closed_local = isset($data['completed_at'])
                ? Carbon::parse($data['completed_at'])
                : now();
            $order->save();
        });

        return response()->json(['ok' => true]);
    }

    public function referenceSourceUpsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'min:1'],
            'source_name' => ['required', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'min:1'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'superpart_partner_id' => ['nullable', 'integer', 'min:1'],
            'superpart_local_source_id' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'available_for_superpart' => ['nullable', 'boolean'],
            'deleted' => ['sometimes', 'boolean'],
        ]);

        $crmSourceId = (int) $data['source_id'];
        $localId = (int) ($data['superpart_local_source_id'] ?? 0);
        $existing = ReferenceSource::query()
            ->where('levelion_source_id', $crmSourceId)
            ->first();
        if (! $existing && $localId > 0) {
            $existing = ReferenceSource::query()
                ->where('local_source_id', $localId)
                ->first();
        }

        if ($request->boolean('deleted')) {
            if ($existing) {
                DB::table('user_allowed_reference_sources')
                    ->where('reference_source_id', $existing->id)
                    ->delete();
                DB::table('partner_phones')
                    ->where('reference_source_id', $existing->id)
                    ->update(['reference_source_id' => null]);
                $existing->delete();
            }

            return response()->json(['ok' => true, 'deleted' => true]);
        }

        $isActive = (bool) ($data['is_active'] ?? true);
        $availableForSuperpart = (bool) ($data['available_for_superpart'] ?? true);

        if (! $isActive || ! $availableForSuperpart) {
            if ($existing) {
                $existing->update([
                    'available_for_superpart' => false,
                    'synced_at' => now(),
                ]);
            }

            return response()->json(['ok' => true]);
        }

        $partnerId = array_key_exists('superpart_partner_id', $data)
            ? ($data['superpart_partner_id'] !== null ? (int) $data['superpart_partner_id'] : null)
            : null;

        if ($existing) {
            $existing->fill([
                'levelion_source_id' => $crmSourceId,
                'name' => $data['source_name'],
                'crm_city_id' => isset($data['city_id']) && (int) $data['city_id'] > 0 ? (int) $data['city_id'] : $existing->crm_city_id,
                'city_name' => $data['city_name'] ?? $existing->city_name,
                'superpart_partner_id' => $partnerId ?? $existing->superpart_partner_id,
                'available_for_superpart' => true,
                'synced_at' => now(),
            ]);
            $existing->save();

            return response()->json(['ok' => true, 'linked' => true]);
        }

        ReferenceSource::updateOrCreate(
            ['levelion_source_id' => $crmSourceId],
            [
                'name' => $data['source_name'],
                'crm_city_id' => isset($data['city_id']) && (int) $data['city_id'] > 0 ? (int) $data['city_id'] : null,
                'city_name' => $data['city_name'] ?? null,
                'superpart_partner_id' => $partnerId,
                'available_for_superpart' => true,
                'synced_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function partnerOrderCreated(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'partner_user_id' => ['required', 'integer', 'min:1'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:50'],
            'city_id' => ['required', 'integer', 'min:1'],
            'street' => ['required', 'string', 'max:255'],
            'house' => ['nullable', 'string', 'max:64'],
            'flat' => ['nullable', 'string', 'max:32'],
            'address_adds' => ['nullable', 'string', 'max:2000'],
            'order_core' => ['nullable', 'string', 'max:32'],
            'equipment_type' => ['nullable', 'string', 'max:64'],
            'datetime_order' => ['required', 'string', 'max:40'],
            'order_adds' => ['nullable', 'string'],
            'order_type' => ['nullable', 'string', 'max:32'],
            'order_status' => ['nullable', 'string', 'max:64'],
            'marketing_source_name' => ['nullable', 'string', 'max:255'],
        ]);

        $crmOrderId = (int) $data['order_id'];

        $existing = Order::query()
            ->whereCrmRecord($crmOrderId)
            ->first();

        if (! $existing) {
            OrderCrmIdSync::vacateOccupantIfUnsynced($crmOrderId);
        }

        if ($existing) {
            $referenceSourceId = null;
            if (! empty($data['source_id'])) {
                $name = isset($data['marketing_source_name']) && is_string($data['marketing_source_name'])
                    ? $data['marketing_source_name']
                    : null;
                $referenceSourceId = ReferenceSource::ensureFromCrmSource((int) $data['source_id'], $name)->id;
            }

            $updates = [];
            if ($referenceSourceId) {
                $updates['reference_source_id'] = $referenceSourceId;
                $updates['source_id'] = null;
            }
            if ((int) ($existing->levelion_order_id ?? 0) !== $crmOrderId) {
                $updates['levelion_order_id'] = $crmOrderId;
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            return response()->json(['ok' => true, 'duplicate' => true, 'updated' => $updates !== []]);
        }

        if (! User::query()->whereKey($data['partner_user_id'])->exists()) {
            return response()->json(['message' => 'Партнёр не найден'], 422);
        }

        $city = City::query()
            ->where('levelion_city_id', $data['city_id'])
            ->first();

        if (! $city) {
            $city = City::create([
                'name' => 'Город CRM #'.$data['city_id'],
                'levelion_city_id' => $data['city_id'],
                'is_available' => true,
                'load_percentage' => 0,
            ]);
        }

        $addressLine = trim(implode(' ', array_filter([
            $data['street'],
            (($data['house'] ?? '') !== '' ? 'д. '.$data['house'] : ''),
            isset($data['flat']) && $data['flat'] !== '' ? 'кв. '.$data['flat'] : '',
        ])));

        $dt = Carbon::parse($data['datetime_order']);

        $typeMap = [
            'new' => 'first_time',
            'repeat' => 'repeat',
            'warranty' => 'warranty',
        ];
        $type = $typeMap[$data['order_type'] ?? ''] ?? 'first_time';

        $referenceSourceId = null;
        if (! empty($data['source_id'])) {
            $name = isset($data['marketing_source_name']) && is_string($data['marketing_source_name'])
                ? $data['marketing_source_name']
                : null;
            $referenceSourceId = ReferenceSource::ensureFromCrmSource((int) $data['source_id'], $name)->id;
        }

        $house = trim((string) ($data['house'] ?? ''));
        if ($house === '') {
            $house = 'б/н';
        }

        $order = new Order([
            'user_id' => $data['partner_user_id'],
            'city_id' => $city->id,
            'status' => 'not_processed',
            'type' => $type,
            'source_id' => null,
            'reference_source_id' => $referenceSourceId,
            'work_type_id' => null,
            'order_time' => $dt,
            'client_name' => $data['client_name'],
            'client_phone' => $data['client_phone'],
            'is_non_profile' => ($data['order_core'] ?? '') === 'non_core',
            'settlement' => null,
            'address' => $addressLine !== '' ? $addressLine : null,
            'street' => $data['street'],
            'house' => $house,
            'flat' => $data['flat'] ?? null,
            'address_adds' => $data['address_adds'] ?? null,
            'order_adds' => $data['order_adds'] ?? null,
            'employee_id' => null,
            'charge_amount' => null,
            'created_local' => now(),
            'closed_local' => null,
            'sync_status' => 'synced',
            'equipment_type' => $data['equipment_type'] ?? null,
            'order_core' => $data['order_core'] ?? null,
        ]);

        $order->id = $crmOrderId;
        $order->levelion_order_id = $crmOrderId;
        $order->save();

        return response()->json(['ok' => true]);
    }
}
