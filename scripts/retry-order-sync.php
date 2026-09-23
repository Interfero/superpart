<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\LevelionApiService;

$id = (int) ($argv[1] ?? 50);
$order = Order::query()->with(['city', 'referenceSource'])->find($id);

if (! $order) {
    echo "Order {$id} not found\n";
    exit(1);
}

$api = app(LevelionApiService::class);

if (! $api->isConfigured()) {
    echo "CRM not configured\n";
    exit(1);
}

if ($order->levelion_order_id) {
    echo "Already synced as CRM #{$order->levelion_order_id}\n";
    exit(0);
}

$push = $api->pushPartnerOrder($order, LevelionApiService::newIdempotencyKey());
$order->levelion_order_id = $push['crm_id'];
$order->sync_status = $push['crm_id'] !== null ? 'synced' : 'error';
$order->sync_last_error = $push['error'];
$order->save();

echo "sync_status={$order->sync_status}\n";
echo "levelion_order_id=".($order->levelion_order_id ?? 'null')."\n";
echo "error=".($order->sync_last_error ?? 'null')."\n";
