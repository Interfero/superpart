<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\City;
use Illuminate\Support\Facades\DB;

$total = City::count();
$uniqNames = City::query()->distinct()->count('name');
$withLevelion = City::query()->whereNotNull('levelion_city_id')->count();

$dupes = DB::table('cities')
    ->select('name', DB::raw('COUNT(*) as c'))
    ->groupBy('name')
    ->having('c', '>', 1)
    ->orderByDesc('c')
    ->limit(5)
    ->get();

echo "total={$total} uniq_names={$uniqNames} with_levelion={$withLevelion}\n";
foreach ($dupes as $row) {
    echo "{$row->name}: {$row->c}\n";
}
