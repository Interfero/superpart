<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Support\LocalSourceReferenceMirror;
use App\Support\OrderSourceOptions;

echo "=== Local sources (last 10) ===\n";
Source::query()->orderByDesc('id')->limit(10)->get()->each(function (Source $s) {
    echo "source id={$s->id} user_id={$s->user_id} name={$s->name}\n";
});

echo "\n=== Reference mirrors (local_source_id not null) ===\n";
ReferenceSource::query()->whereNotNull('local_source_id')->orderByDesc('id')->get()->each(function (ReferenceSource $r) {
    echo "ref id={$r->id} local={$r->local_source_id} levelion={$r->levelion_source_id} partner={$r->superpart_partner_id} avail=".($r->available_for_superpart ? '1' : '0')." name={$r->name}\n";
});

echo "\n=== СЕРВИС sources ===\n";
Source::query()->where('name', 'like', '%СЕРВИС%')->get()->each(function (Source $s) {
    echo "source id={$s->id} user_id={$s->user_id} name={$s->name}\n";
    $ref = ReferenceSource::query()->where('local_source_id', $s->id)->first();
    echo $ref
        ? "  mirror ref id={$ref->id} avail=".($ref->available_for_superpart ? '1' : '0')." partner={$ref->superpart_partner_id}\n"
        : "  NO MIRROR\n";
});

echo "\n=== All partners with sources ===\n";
User::query()->where('role', User::ROLE_PARTNER)->orderBy('id')->get()->each(function (User $p) {
    $srcCount = Source::query()->where('user_id', $p->id)->count();
    if ($srcCount === 0) {
        return;
    }
    echo "Partner {$p->id} ({$p->email}): {$srcCount} local sources\n";
    LocalSourceReferenceMirror::syncAllForOwner($p->id);
    $list = ReferenceSource::queryForOrderForm($p)->pluck('name')->all();
    echo "  order form: ".implode(' | ', $list)."\n";
});

echo "\n=== Users with role developer/gd who own sources ===\n";
User::query()->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_GENERAL_DIRECTOR, User::ROLE_PARTNER])->orderBy('id')->get()->each(function (User $u) {
    $srcCount = Source::query()->where('user_id', $u->id)->count();
    echo "User {$u->id} ({$u->email}) role={$u->role} local_sources={$srcCount}\n";
    $list = OrderSourceOptions::referenceSourcesForUser($u)->pluck('name')->all();
    echo "  order form: ".($list === [] ? '—' : implode(' | ', $list))."\n";
    foreach ($u->managedUsers()->where('role', User::ROLE_MANAGER)->get() as $manager) {
        $mlist = OrderSourceOptions::referenceSourcesForUser($manager)->pluck('name')->all();
        echo "  manager {$manager->id}: ".($mlist === [] ? '—' : implode(' | ', $mlist))."\n";
    }
});

echo "\n=== has column local_source_id ===\n";
echo Illuminate\Support\Facades\Schema::hasColumn('reference_sources', 'local_source_id') ? "yes\n" : "no\n";
