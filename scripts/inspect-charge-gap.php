<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Who owns waiting_payment orders vs who got charges
$waiting = Order::query()->where('status', 'waiting_payment')->where('charge_amount', '>', 0)->count();
$withChargeNoTx = Order::query()
    ->where('charge_amount', '>', 0)
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))
            ->from('transactions')
            ->whereColumn('transactions.order_id', 'orders.id')
            ->where('transactions.operation_type', 'charge');
    })
    ->count();

echo "waiting_payment_with_charge={$waiting}\n";
echo "orders_with_charge_without_tx={$withChargeNoTx}\n";

$samples = Order::query()
    ->where('charge_amount', '>', 0)
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))
            ->from('transactions')
            ->whereColumn('transactions.order_id', 'orders.id')
            ->where('transactions.operation_type', 'charge');
    })
    ->limit(10)
    ->get(['id', 'user_id', 'charge_amount', 'status', 'type']);

foreach ($samples as $o) {
    echo "order={$o->id} user={$o->user_id} charge={$o->charge_amount} status={$o->status}\n";
}

$partner = User::find(8);
echo "\npartner8 balance={$partner->balance}\n";

$dev = User::find(10);
echo "dev10 balance={$dev->balance}\n";
echo "dev can see all txs: elevated=".($dev->hasElevatedAccess() ? 'yes' : 'no')."\n";
