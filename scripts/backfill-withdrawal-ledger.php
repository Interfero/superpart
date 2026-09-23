<?php
/**
 * Досписать проводки списания для completed-выплат без ledger_transaction_id
 * и пересчитать users.balance.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WithdrawalRequest;
use App\Support\UserBalance;

$done = WithdrawalRequest::query()
    ->where('status', 'completed')
    ->whereNull('ledger_transaction_id')
    ->orderBy('id')
    ->get();

echo 'pending_ledger='.$done->count().PHP_EOL;

foreach ($done as $w) {
    try {
        $r = UserBalance::applyWithdrawal($w);
        echo "withdrawal#{$w->id} amount={$w->total_amount} created=".($r['created']?'1':'0')." balance={$r['balance']}\n";
    } catch (Throwable $e) {
        echo "withdrawal#{$w->id} ERROR ".$e->getMessage().PHP_EOL;
    }
}

$synced = UserBalance::syncAll();
echo "synced_users=$synced\n";
