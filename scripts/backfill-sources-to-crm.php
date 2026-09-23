<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;
use App\Support\LocalSourceReferenceMirror;

if (! LocalSourceReferenceMirror::isActive()) {
    echo "CRM integration is not configured.\n";
    exit(1);
}

$count = 0;

Source::query()->orderBy('id')->each(function (Source $source) use (&$count) {
    $ref = LocalSourceReferenceMirror::sync($source);
    $crmId = $ref?->levelion_source_id ?? '—';
    echo "source #{$source->id} ({$source->name}) owner={$source->user_id} crm={$crmId}\n";
    $count++;
});

echo "Synced {$count} sources.\n";
