<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\City;
use Illuminate\Support\Facades\DB;

$beforeTotal = City::count();

$removed = City::dedupeByName();

$afterTotal = City::count();

echo "removed={$removed} total {$beforeTotal}->{$afterTotal}\n";

$remaining = DB::table('cities')
    ->selectRaw('name, COUNT(*) as c')
    ->groupBy('name')
    ->having('c', '>', 1)
    ->orderByDesc('c')
    ->limit(10)
    ->get();

if ($remaining->isEmpty()) {
    echo "no duplicate names left\n";
} else {
    foreach ($remaining as $row) {
        echo "still duplicated: {$row->name} x{$row->c}\n";
    }
}
