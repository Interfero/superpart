<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Support\OrderCrmIdSync;
use Illuminate\Support\Facades\DB;

$row = DB::table('orders')
    ->whereNotNull('levelion_order_id')
    ->whereColumn('id', '!=', 'levelion_order_id')
    ->orderBy('id')
    ->first(['id', 'levelion_order_id']);

if (! $row && isset($argv[1])) {
    $row = DB::table('orders')->where('id', (int) $argv[1])->first(['id', 'levelion_order_id']);
}

if (! $row) {
    echo "nothing to align\n";
    exit(0);
}

echo "try id={$row->id} crm={$row->levelion_order_id}\n";

$conflict = Order::query()->whereKey($row->levelion_order_id)->where('id', '!=', $row->id)->exists();
echo "conflict=".($conflict ? 'yes' : 'no')."\n";

$order = Order::query()->find($row->id);

try {
    $result = OrderCrmIdSync::align($order, (int) $row->levelion_order_id);
    echo "ok new_id={$result->id}\n";
} catch (Throwable $e) {
    echo "error: ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
    exit(1);
}
