<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;
use App\Models\User;
use App\Support\LocalSourceReferenceMirror;
use App\Support\OrderSourceOptions;

$sourceId = (int) ($argv[1] ?? 31);
$targetUserId = (int) ($argv[2] ?? 8);

$source = Source::query()->findOrFail($sourceId);
$target = User::query()->findOrFail($targetUserId);

echo "Move source #{$source->id} ({$source->name}) from user {$source->user_id} to {$target->id} ({$target->email}, {$target->role})\n";

$source->user_id = $target->id;
$source->save();

$ref = LocalSourceReferenceMirror::sync($source);
echo $ref ? "Mirrored as reference_source #{$ref->id}\n" : "Mirror skipped (CRM off)\n";

$list = OrderSourceOptions::referenceSourcesForUser($target)->pluck('name')->all();
echo "Partner order form: ".implode(' | ', $list)."\n";
