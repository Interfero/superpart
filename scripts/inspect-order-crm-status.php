<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\User;
use App\Services\LevelionApiService;
use Illuminate\Support\Facades\DB;

echo "=== order status counts ===\n";
$rows = DB::table('orders')
    ->selectRaw('status, COUNT(*) as cnt')
    ->groupBy('status')
    ->orderByDesc('cnt')
    ->get();
foreach ($rows as $r) {
    echo "{$r->status}\t{$r->cnt}\n";
}

echo "\n=== sample recent orders ===\n";
$orders = Order::query()->orderByDesc('id')->limit(15)->get(['id', 'status', 'type', 'levelion_order_id', 'sync_status', 'charge_amount', 'user_id']);
foreach ($orders as $o) {
    echo "id={$o->id} status={$o->status} type={$o->type} crm=".($o->levelion_order_id ?? 'null')." sync={$o->sync_status} charge=".($o->charge_amount ?? 'null')." user={$o->user_id}\n";
}

$api = app(LevelionApiService::class);
echo "\nCRM configured=".($api->isConfigured() ? 'yes' : 'no')."\n";

$sample = Order::query()->whereNotNull('levelion_order_id')->orderByDesc('id')->limit(5)->get();
foreach ($sample as $o) {
    $data = $api->fetchPartnerOrderFromCrm((int) $o->crmOrderId());
    $crmStatus = is_array($data) ? ($data['order_status'] ?? 'n/a') : 'fetch_fail';
    echo "order={$o->id} pp_status={$o->status} crm_status={$crmStatus}\n";
    if (is_array($data)) {
        echo "  payload=".json_encode($data, JSON_UNESCAPED_UNICODE)."\n";
    }
}

$partner = User::find(8);
if ($partner) {
    echo "\npartner8 balance={$partner->balance} wallet=".$partner->walletBalance()." show_nav=".($partner->showsWalletInNavbar()?'1':'0')."\n";
}
$dev = User::find(10);
if ($dev) {
    echo "dev10 balance={$dev->balance} wallet=".$dev->walletBalance()." show_nav=".($dev->showsWalletInNavbar()?'1':'0')." elevated=".($dev->hasElevatedAccess()?'1':'0')."\n";
}
