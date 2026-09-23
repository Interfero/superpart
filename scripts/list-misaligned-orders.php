<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('orders')
    ->whereNotNull('levelion_order_id')
    ->whereColumn('id', '!=', 'levelion_order_id')
    ->orderBy('id')
    ->get(['id', 'levelion_order_id']);

foreach ($rows as $row) {
    $conflict = DB::table('orders')->where('id', $row->levelion_order_id)->where('id', '!=', $row->id)->exists();
    echo "id={$row->id} crm={$row->levelion_order_id} conflict=".($conflict ? 'yes' : 'no')."\n";
}

echo "count=".$rows->count()."\n";
