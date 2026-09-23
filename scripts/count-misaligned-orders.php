<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$misaligned = DB::table('orders')
    ->whereNotNull('levelion_order_id')
    ->whereColumn('id', '!=', 'levelion_order_id')
    ->count();

$withCrm = DB::table('orders')->whereNotNull('levelion_order_id')->count();
$total = DB::table('orders')->count();

echo "total={$total}\n";
echo "with_crm_id={$withCrm}\n";
echo "misaligned={$misaligned}\n";
