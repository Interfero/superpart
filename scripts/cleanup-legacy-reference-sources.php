<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\ReferenceSource;
use Illuminate\Support\Facades\DB;

DB::reconnect();

$legacyIds = ReferenceSource::query()
    ->whereNull('local_source_id')
    ->pluck('id');

if ($legacyIds->isEmpty()) {
    echo "No legacy CRM-only reference_sources rows.\n";
    exit(0);
}

$usedIds = Order::query()
    ->whereIn('reference_source_id', $legacyIds)
    ->distinct()
    ->pluck('reference_source_id');

$unusedIds = $legacyIds->diff($usedIds);

if ($unusedIds->isNotEmpty()) {
    DB::table('user_allowed_reference_sources')
        ->whereIn('reference_source_id', $unusedIds->all())
        ->delete();

    DB::table('partner_phones')
        ->whereIn('reference_source_id', $unusedIds->all())
        ->update(['reference_source_id' => null]);

    foreach ($unusedIds as $id) {
        DB::delete('DELETE FROM reference_sources WHERE id = ?', [(int) $id]);
    }

    echo 'Deleted '.$unusedIds->count()." unused legacy rows.\n";
}

if ($usedIds->isNotEmpty()) {
    ReferenceSource::query()
        ->whereIn('id', $usedIds)
        ->update(['available_for_superpart' => false]);

    echo 'Marked '.$usedIds->count()." legacy rows as inactive (used in orders).\n";
}

$remaining = ReferenceSource::query()->whereNull('local_source_id')->count();
echo "Remaining legacy rows: {$remaining}\n";
