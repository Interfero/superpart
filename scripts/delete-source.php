<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;
use App\Support\LocalSourceReferenceMirror;

$sourceId = (int) ($argv[1] ?? 0);

if ($sourceId < 1) {
    echo "usage: delete-source.php <source_id>\n";
    exit(1);
}

$source = Source::query()->find($sourceId);

if (! $source) {
    echo "source {$sourceId} not found\n";
    exit(1);
}

echo "deleting source {$source->id} {$source->name} user_id={$source->user_id}\n";

try {
    LocalSourceReferenceMirror::forget($source);
    echo "mirror forgotten\n";
    $source->delete();
    echo "soft-deleted ok\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
