<?php

namespace App\Support;

use App\Models\Order;
use App\Services\LevelionApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class OrderCrmIdSync
{
    /** Локальные заявки, которые ещё не получили ID CRM — вне живой нумерации. */
    public const PARKED_ID_MIN = 90_000_000;

    /** @var list<string> */
    private const ORDER_ID_TABLES = [
        'transactions',
        'reviews',
        'withdrawal_request_items',
    ];

    public static function isParkedId(int $id): bool
    {
        return $id >= self::PARKED_ID_MIN;
    }

    /**
     * Если на боевом ID лежит черновик портала — увести его из нумерации CRM.
     */
    public static function vacateOccupantIfUnsynced(int $id): void
    {
        $occupant = Order::query()->whereKey($id)->first();
        if (! $occupant || ! self::isUnsyncedLocal($occupant)) {
            return;
        }

        $freeId = self::nextParkedId();
        Log::warning('OrderCrmIdSync: relocating unsynced local order to avoid CRM id clash', [
            'from_id' => (int) $occupant->id,
            'to_id' => $freeId,
            'crm_id' => $id,
        ]);
        self::relocate($occupant, $freeId);
    }

    public static function nextLiveId(): int
    {
        $max = (int) DB::table('orders')->where('id', '<', self::PARKED_ID_MIN)->max('id');
        $candidate = max($max, 0) + 1;
        while ($candidate < self::PARKED_ID_MIN && Order::query()->whereKey($candidate)->exists()) {
            $candidate++;
        }

        return $candidate;
    }

    /**
     * Сохранить новую заявку с ID из CRM (создание сначала в CRM).
     */
    public static function insertAsCrmOrder(Order $order, int $crmId): Order
    {
        if ($crmId < 1) {
            return $order;
        }

        $existing = Order::query()->whereKey($crmId)->first();
        if ($existing && (int) $existing->id !== (int) ($order->id ?? 0)) {
            if (self::isUnsyncedLocal($existing)) {
                self::relocate($existing, self::nextParkedId());
            } elseif (self::samePartnerOrderFingerprint($existing, $order)) {
                // CRM вернула тот же order_id по Idempotency-Key — второй черновик не создаём.
                return $existing;
            } else {
                Log::error('OrderCrmIdSync: CRM id already used by synced order, parking new row', [
                    'crm_id' => $crmId,
                ]);
                $order->sync_status = 'error';
                $order->sync_last_error = 'Номер заявки CRM уже занят в портале. Нажмите «Повторить отправку».';

                return self::persistParked($order);
            }
        }

        return self::persistWithExplicitId($order, $crmId, [
            'levelion_order_id' => $crmId,
            'sync_status' => 'synced',
            'sync_last_error' => null,
        ]);
    }

    /**
     * Сохранить заявку, которая не ушла в CRM, вне диапазона боевых ID.
     */
    public static function persistParked(Order $order): Order
    {
        if ($order->exists) {
            return self::relocate($order, self::nextParkedId());
        }

        return self::persistWithExplicitId($order, self::nextParkedId(), [
            'levelion_order_id' => null,
        ]);
    }

    public static function isUnsyncedLocal(Order $order): bool
    {
        return in_array((string) $order->sync_status, ['error', 'pending'], true)
            || ((int) ($order->crm_sync_version ?? 0) === 0 && filled($order->sync_last_error))
            || self::isParkedId((int) $order->id);
    }

    /**
     * Присвоить заявке в SuperPart тот же числовой ID, что вернула CRM.
     */
    public static function align(Order $order, int $crmId): Order
    {
        if ($crmId < 1) {
            return $order;
        }

        if ((int) $order->id === $crmId) {
            if ((int) $order->levelion_order_id !== $crmId) {
                $order->levelion_order_id = $crmId;
                $order->save();
            }

            return $order->fresh() ?? $order;
        }

        $conflict = Order::query()
            ->whereKey($crmId)
            ->where('id', '!=', $order->id)
            ->first();

        if ($conflict) {
            if (self::isUnsyncedLocal($conflict)) {
                self::relocate($conflict, self::nextParkedId());
            } else {
                Log::warning('OrderCrmIdSync: refuse align onto synced CRM row', [
                    'from_id' => (int) $order->id,
                    'crm_id' => $crmId,
                ]);
                $order->sync_last_error = 'Нельзя присвоить ID CRM: он уже занят другой заявкой.';
                $order->save();

                return $order->fresh() ?? $order;
            }
        }

        $oldId = (int) $order->id;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('orders')->where('id', $oldId)->update([
                'id' => $crmId,
                'levelion_order_id' => $crmId,
            ]);

            foreach (self::ORDER_ID_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->where('order_id', $oldId)->update(['order_id' => $crmId]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        self::bumpAutoIncrement();

        return Order::query()->findOrFail($crmId);
    }

    /**
     * Увести локальную несинхронизированную заявку на свободный ID,
     * чтобы снимок CRM с тем же номером её не затёр.
     */
    public static function relocate(Order $order, int $newId): Order
    {
        if ($newId < 1 || (int) $order->id === $newId) {
            return $order;
        }

        $conflict = Order::query()->whereKey($newId)->exists();
        if ($conflict) {
            throw new \RuntimeException('Нельзя перенести заявку: ID '.$newId.' уже занят.');
        }

        $oldId = (int) $order->id;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('orders')->where('id', $oldId)->update([
                'id' => $newId,
                'levelion_order_id' => null,
            ]);

            foreach (self::ORDER_ID_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->where('order_id', $oldId)->update(['order_id' => $newId]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        self::bumpAutoIncrement();

        return Order::query()->findOrFail($newId);
    }

    public static function nextParkedId(): int
    {
        $maxParked = (int) DB::table('orders')->where('id', '>=', self::PARKED_ID_MIN)->max('id');

        return max(self::PARKED_ID_MIN, $maxParked) + 1;
    }

    /**
     * MySQL не опускает AUTO_INCREMENT ниже max(id). После parked-рядов
     * (90xxxxxx) полагаемся на явный id при INSERT, а не на автоинкремент.
     */
    public static function bumpAutoIncrement(): void
    {
        if (DB::table('orders')->where('id', '>=', self::PARKED_ID_MIN)->exists()) {
            return;
        }

        $max = (int) DB::table('orders')->where('id', '<', self::PARKED_ID_MIN)->max('id');

        if ($max < 1) {
            return;
        }

        DB::statement('ALTER TABLE orders AUTO_INCREMENT = '.($max + 1));
    }

    private static function samePartnerOrderFingerprint(Order $existing, Order $incoming): bool
    {
        return hash_equals(
            LevelionApiService::partnerOrderIdempotencyKey($existing),
            LevelionApiService::partnerOrderIdempotencyKey($incoming)
        );
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private static function persistWithExplicitId(Order $order, int $id, array $attrs = []): Order
    {
        foreach ($attrs as $key => $value) {
            $order->{$key} = $value;
        }

        $order->id = $id;
        $order->incrementing = false;
        $order->save();
        $order->incrementing = true;

        self::bumpAutoIncrement();

        return $order->fresh() ?? $order;
    }
}
