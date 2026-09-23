<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\LevelionApiService;

$id = (int) ($argv[1] ?? 46);

$order = Order::query()->find($id);

if (! $order) {
    echo "Order {$id} not found\n";
    exit(1);
}

echo "id={$order->id}\n";
echo "levelion_order_id=" . ($order->levelion_order_id ?? 'null') . "\n";
echo "status={$order->status}\n";
echo "sync_status={$order->sync_status}\n";
echo "sync_last_error=" . ($order->sync_last_error ?? 'null') . "\n";

$api = app(LevelionApiService::class);

if ($order->levelion_order_id && $api->isConfigured()) {
    $data = $api->fetchPartnerOrderFromCrm((int) $order->levelion_order_id);
    echo "crm_fetch=" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
