<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\LevelionApiService;
use App\Support\OrderCrmIdSync;
use PHPUnit\Framework\TestCase;

class OrderCrmIdTest extends TestCase
{
    public function test_unsynced_local_id_is_not_a_crm_id(): void
    {
        $order = new Order;
        $order->id = 597;
        $order->sync_status = 'error';
        $order->levelion_order_id = null;

        $this->assertNull($order->crmOrderId());
        $this->assertTrue(OrderCrmIdSync::isUnsyncedLocal($order));
    }

    public function test_synced_order_uses_levelion_id(): void
    {
        $order = new Order;
        $order->id = 598;
        $order->sync_status = 'synced';
        $order->levelion_order_id = 598;

        $this->assertSame(598, $order->crmOrderId());
        $this->assertFalse(OrderCrmIdSync::isUnsyncedLocal($order));
    }

    public function test_parked_id_range(): void
    {
        $this->assertFalse(OrderCrmIdSync::isParkedId(597));
        $this->assertTrue(OrderCrmIdSync::isParkedId(90_000_001));
    }

    public function test_parked_order_is_not_a_crm_record_id(): void
    {
        $order = new Order;
        $order->id = 90_000_001;
        $order->sync_status = 'error';
        $order->levelion_order_id = null;

        $this->assertTrue($order->isParked());
        $this->assertNull($order->crmOrderId());
        $this->assertSame('черновик #1', $order->displayNumber());
        $this->assertTrue(OrderCrmIdSync::isUnsyncedLocal($order));
    }

    public function test_partner_push_idempotency_key_is_stable(): void
    {
        $order = new Order;
        $order->user_id = 12;
        $order->client_phone = '9001234567';
        $order->order_time = '2026-09-14 12:00:00';
        $order->street = 'Ленина';
        $order->house = '1';
        $order->flat = '2';
        $order->order_adds = 'Настроить роутер';

        $a = LevelionApiService::partnerOrderIdempotencyKey($order);
        $b = LevelionApiService::partnerOrderIdempotencyKey($order);

        $this->assertSame($a, $b);
        $this->assertStringStartsWith('sp-po-', $a);
        $this->assertNotSame($a, LevelionApiService::newIdempotencyKey());
    }
}
