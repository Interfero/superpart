<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$col = DB::selectOne("SHOW COLUMNS FROM orders LIKE 'status'");
echo "status_type=".$col->Type."\n";

$rows = DB::table('orders')->where('type', 'warranty')->get(['id', 'status', 'charge_amount', 'closed_local']);
echo "warranty_orders=".$rows->count()."\n";
foreach ($rows as $r) {
    $tx = DB::table('transactions')->where('order_id', $r->id)->where('operation_type', 'charge')->sum('amount');
    echo "id={$r->id} status={$r->status} charge=".($r->charge_amount ?? 'null')." tx_charge={$tx}\n";
}

$refusalZero = DB::table('orders')
    ->where('status', 'refusal')
    ->where(function ($q) {
        $q->whereNull('charge_amount')->orWhere('charge_amount', '<=', 0);
    })
    ->selectRaw('type, COUNT(*) as cnt')
    ->groupBy('type')
    ->get();
echo "\nrefusal_zero_charge_by_type:\n";
foreach ($refusalZero as $r) {
    echo "{$r->type}\t{$r->cnt}\n";
}
