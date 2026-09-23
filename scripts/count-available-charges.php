<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Transaction;
use App\Models\WithdrawalRequestItem;

$used = WithdrawalRequestItem::pluck('transaction_id');
$base = Transaction::query()
    ->where('operation_type', 'charge')
    ->where('amount', '>', 0)
    ->whereNotIn('id', $used);

echo 'available_all='.$base->count()."\n";
echo 'available_user8='.(clone $base)->where('user_id', 8)->count()."\n";
echo 'available_user10='.(clone $base)->where('user_id', 10)->count()."\n";
echo 'sum_all='.(clone $base)->sum('amount')."\n";
