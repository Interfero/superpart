<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "--- all users with txs or nonzero balance ---\n";
$users = User::query()->orderBy('id')->get(['id', 'name', 'role', 'balance', 'parent_user_id']);
foreach ($users as $u) {
    $lastTx = Transaction::query()->where('user_id', $u->id)->orderByDesc('id')->first();
    $cnt = Transaction::query()->where('user_id', $u->id)->count();
    if ($cnt === 0 && (float) $u->balance == 0) {
        continue;
    }
    $expected = $lastTx ? (float) $lastTx->balance_after : 0.0;
    $actual = (float) $u->balance;
    $mark = abs($expected - $actual) > 0.009 ? ' MISMATCH' : ' ok';
    echo "id={$u->id} role={$u->role} name={$u->name} balance={$actual} expected={$expected} txs={$cnt}{$mark}\n";
}

echo "\n--- sample last 5 txs ---\n";
foreach (Transaction::query()->orderByDesc('id')->limit(5)->get() as $t) {
    echo "tx={$t->id} user={$t->user_id} amount={$t->amount} after={$t->balance_after} type={$t->operation_type}\n";
}
