<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\LevelionApiService;

$api = app(LevelionApiService::class);
$orders = Order::query()
    ->whereNotNull('levelion_order_id')
    ->whereIn('status', ['not_processed', 'waiting', 'in_work', 'clarification'])
    ->orderByDesc('id')
    ->limit(40)
    ->get();

$crmStatuses = [];
foreach ($orders as $o) {
    $data = $api->fetchPartnerOrderFromCrm((int) $o->crmOrderId());
    $crm = is_array($data) ? (string) ($data['order_status'] ?? '') : 'fail';
    $crmStatuses[$crm] = ($crmStatuses[$crm] ?? 0) + 1;
    if (($crmStatuses[$crm] ?? 0) <= 2) {
        echo "pp={$o->id}/{$o->status} crm={$crm}\n";
    }
}

echo "\n--- CRM status histogram ---\n";
foreach ($crmStatuses as $s => $c) {
    echo "{$s}\t{$c}\n";
}
