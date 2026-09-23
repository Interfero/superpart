<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\LevelionApiService;
use Illuminate\Support\Facades\DB;

$api = app(LevelionApiService::class);

if (! $api->isConfigured()) {
    echo "CRM not configured\n";
    exit(1);
}

$orders = Order::query()
    ->whereNotNull('levelion_order_id')
    ->whereNotIn('status', ['waiting_payment', 'warranty'])
    ->orderBy('id')
    ->get();

$changed = 0;
$skipped = 0;

foreach ($orders as $order) {
    $before = $order->status;
    try {
        $api->syncOrderStatusFromCrmIfConfigured($order);
        $order->refresh();
        if ($order->status !== $before) {
            echo "id={$order->id} {$before} -> {$order->status}\n";
            $changed++;
        } else {
            $skipped++;
        }
    } catch (Throwable $e) {
        echo "fail id={$order->id}: {$e->getMessage()}\n";
    }
}

echo "\nchanged={$changed} unchanged={$skipped}\n";
echo "--- status counts ---\n";
foreach (DB::table('orders')->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->orderByDesc('cnt')->get() as $r) {
    echo "{$r->status}\t{$r->cnt}\n";
}
