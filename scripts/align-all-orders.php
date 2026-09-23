<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Support\OrderCrmIdSync;
use Illuminate\Support\Facades\DB;

$aligned = 0;
$failed = 0;

while (true) {
    $row = DB::table('orders')
        ->whereNotNull('levelion_order_id')
        ->whereColumn('id', '!=', 'levelion_order_id')
        ->orderBy('id')
        ->first(['id', 'levelion_order_id']);

    if (! $row) {
        break;
    }

    $order = Order::query()->find($row->id);

    if (! $order) {
        echo "missing order id={$row->id}\n";
        $failed++;
        break;
    }

    try {
        $before = (int) $order->id;
        $result = OrderCrmIdSync::align($order, (int) $row->levelion_order_id);
        echo "aligned {$before} -> {$result->id}\n";
        $aligned++;
    } catch (Throwable $e) {
        echo "fail id={$row->id}: {$e->getMessage()}\n";
        $failed++;
        break;
    }
}

$left = DB::table('orders')
    ->whereNotNull('levelion_order_id')
    ->whereColumn('id', '!=', 'levelion_order_id')
    ->count();

echo "aligned={$aligned} failed={$failed} left={$left}\n";
