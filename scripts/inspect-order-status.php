<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\LevelionApiService;
use Illuminate\Support\Facades\DB;

$id = (int) ($argv[1] ?? 276);
$order = Order::query()->find($id);

if (! $order) {
    echo "not found\n";
    exit(1);
}

echo "id={$order->id}\n";
echo "status={$order->status}\n";
echo "type={$order->type}\n";
echo "charge={$order->charge_amount}\n";
echo "closed={$order->closed_local}\n";
echo "sync={$order->sync_status}\n";
echo "levelion={$order->levelion_order_id}\n";
echo "is_non_profile=".($order->is_non_profile ? '1' : '0')."\n";

$stats = DB::table('orders')
    ->selectRaw('status, type, COUNT(*) as cnt')
    ->groupBy('status', 'type')
    ->orderBy('status')
    ->orderBy('type')
    ->get();

echo "\n--- status/type counts ---\n";
foreach ($stats as $row) {
    echo "{$row->status}\t{$row->type}\t{$row->cnt}\n";
}

$warrantyAsRefusal = Order::query()
    ->where('type', 'warranty')
    ->whereIn('status', ['refusal', 'refusal_non_profile', 'cancelled'])
    ->count();
echo "\nwarranty_marked_refusal_like={$warrantyAsRefusal}\n";

$api = app(LevelionApiService::class);
if ($order->crmOrderId() && $api->isConfigured()) {
    $data = $api->fetchPartnerOrderFromCrm((int) $order->crmOrderId());
    echo "\n--- CRM payload ---\n";
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
}
