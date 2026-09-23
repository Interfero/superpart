<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$userId = (int) ($argv[1] ?? 10);
$user = User::query()->find($userId);

if (! $user) {
    echo "user not found\n";
    exit(1);
}

echo "user_id={$user->id}\n";
echo "name={$user->name}\n";
echo "users.balance={$user->balance}\n";

$txCount = Transaction::query()->where('user_id', $userId)->count();
$sumCharge = (float) Transaction::query()->where('user_id', $userId)->where('operation_type', 'charge')->sum('amount');
$sumWithdraw = (float) Transaction::query()->where('user_id', $userId)->whereIn('operation_type', ['withdrawal', 'correction'])->sum('amount');
$last = Transaction::query()->where('user_id', $userId)->orderByDesc('id')->first();

echo "tx_count={$txCount}\n";
echo "sum_charge={$sumCharge}\n";
echo "sum_withdraw_like={$sumWithdraw}\n";
echo "last_balance_after=".($last?->balance_after ?? 'null')."\n";
echo "last_id=".($last?->id ?? 'null')."\n";

// recomputed from all txs chronologically
$recomputed = 0.0;
$rows = Transaction::query()->where('user_id', $userId)->orderBy('id')->get(['id', 'amount', 'operation_type', 'balance_after']);
$mismatches = 0;
foreach ($rows as $row) {
    if ($row->operation_type === 'charge') {
        $recomputed = round($recomputed + (float) $row->amount, 2);
    } else {
        // withdrawal / correction typically decrease
        $recomputed = round($recomputed + (float) $row->amount, 2); // amount may already be signed
    }
}
echo "recomputed_by_sum_amounts={$recomputed}\n";

$amountSignHint = Transaction::query()
    ->where('user_id', $userId)
    ->selectRaw('operation_type, MIN(amount) as min_a, MAX(amount) as max_a, SUM(amount) as sum_a, COUNT(*) as cnt')
    ->groupBy('operation_type')
    ->get();
foreach ($amountSignHint as $h) {
    echo "op={$h->operation_type} cnt={$h->cnt} min={$h->min_a} max={$h->max_a} sum={$h->sum_a}\n";
}

// all users with mismatch
echo "\n--- mismatches users.balance vs last balance_after ---\n";
$users = User::query()->orderBy('id')->get(['id', 'name', 'balance']);
foreach ($users as $u) {
    $lastTx = Transaction::query()->where('user_id', $u->id)->orderByDesc('id')->first();
    $expected = $lastTx ? (float) $lastTx->balance_after : 0.0;
    $actual = (float) ($u->balance ?? 0);
    if (abs($expected - $actual) > 0.009) {
        echo "user={$u->id} balance={$actual} expected={$expected} tx=".($lastTx?->id ?? 0)."\n";
    }
}
