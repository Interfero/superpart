<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$user = User::query()->find(10);
echo "user10 role={$user->role} parent={$user->parent_user_id}\n";
echo "isDeveloper=".($user->isDeveloper() ? '1' : '0')." isAdmin=".($user->isAdmin() ? '1' : '0')." isPartner=".($user->isPartner() ? '1' : '0')." isManager=".($user->isManager() ? '1' : '0')."\n";
$ids = $user->visibleOrderUserIds();
echo "visibleOrderUserIds=".json_encode($ids)."\n";

$last = Transaction::query()->orderByDesc('id')->first();
echo "global_last_tx id={$last?->id} user_id={$last?->user_id} balance_after={$last?->balance_after} amount={$last?->amount}\n";

$rows = DB::table('transactions')
    ->selectRaw('user_id, COUNT(*) as cnt, SUM(amount) as sum_a, MAX(id) as last_id')
    ->groupBy('user_id')
    ->get();
foreach ($rows as $r) {
    $u = User::find($r->user_id);
    $lastTx = Transaction::find($r->last_id);
    echo "tx_user={$r->user_id} name=".($u->name ?? '?')." role=".($u->role ?? '?')." balance=".($u->balance ?? '?')." cnt={$r->cnt} sum={$r->sum_a} last_after=".($lastTx->balance_after ?? '?')."\n";
}

echo "\n--- owner chain for user 10 ---\n";
echo "effectiveOwnerId=".$user->effectiveOwnerId()."\n";
$owner = User::find($user->effectiveOwnerId());
if ($owner) {
    echo "owner id={$owner->id} name={$owner->name} balance={$owner->balance}\n";
    $lastOwner = Transaction::query()->where('user_id', $owner->id)->orderByDesc('id')->first();
    echo "owner last_after=".($lastOwner->balance_after ?? 'null')."\n";
}
