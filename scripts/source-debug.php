<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;

$email = $argv[1] ?? 'teststarsh@test.local';

$user = User::query()->where('email', $email)->first();

if (! $user) {
    echo "user not found: {$email}\n";
    exit(1);
}

echo "user id={$user->id} role={$user->role} name={$user->name}\n";
echo 'elevated=' . ($user->hasElevatedAccess() ? 'yes' : 'no') . "\n";
echo 'manage-sources=' . (Illuminate\Support\Facades\Gate::forUser($user)->allows('manage-sources') ? 'yes' : 'no') . "\n\n";

echo "=== sources (including soft-deleted) ===\n";
foreach (Source::withTrashed()->where('user_id', $user->id)->orderBy('id')->get() as $s) {
    $deleted = $s->deleted_at ? $s->deleted_at->toDateTimeString() : 'active';
    echo "{$s->id}|{$s->name}|{$deleted}\n";
}

echo "\n=== reference_sources for partner ===\n";
$localIds = Source::withTrashed()->where('user_id', $user->id)->pluck('id');

foreach (
    ReferenceSource::query()
        ->where('superpart_partner_id', $user->id)
        ->orWhereIn('local_source_id', $localIds)
        ->orderBy('id')
        ->get() as $r
) {
    echo "{$r->id}|{$r->name}|local={$r->local_source_id}|crm={$r->levelion_source_id}|avail={$r->available_for_superpart}\n";
}

echo "\n=== orphan reference_sources (no local, partner={$user->id}) ===\n";
foreach (
    ReferenceSource::query()
        ->whereNull('local_source_id')
        ->where('superpart_partner_id', $user->id)
        ->orderBy('id')
        ->get() as $r
) {
    echo "{$r->id}|{$r->name}|crm={$r->levelion_source_id}\n";
}

echo "\n=== all active sources ===\n";
foreach (Source::query()->with('user:id,email,role')->orderBy('id')->get() as $s) {
    echo "{$s->id}|{$s->name}|user={$s->user_id}|{$s->user?->email}|role={$s->user?->role}\n";
}

echo "\n=== reference_sources without local_source (legacy CRM) ===\n";
foreach (ReferenceSource::query()->whereNull('local_source_id')->orderBy('id')->get() as $r) {
    echo "{$r->id}|{$r->name}|partner={$r->superpart_partner_id}|crm={$r->levelion_source_id}|avail={$r->available_for_superpart}\n";
}
