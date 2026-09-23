<?php
/**
 * §21 ТЗ — smoke-проверка ACL источников/заказов на бою (read-only + asserts).
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\PartnerSourceAccessLog;
use App\Models\ReferenceSource;
use App\Models\User;
use App\Support\OrderSourceFilter;
use App\Support\PartnerSourceAccess;

$fail = 0;
function ok(string $msg): void { echo "[OK] $msg\n"; }
function bad(string $msg): void { global $fail; $fail++; echo "[FAIL] $msg\n"; }

$partners = User::query()->where('role', User::ROLE_PARTNER)->limit(5)->get();
ok('partners_sample='.$partners->count());

foreach ($partners as $p) {
    $ids = PartnerSourceAccess::accessibleSourceIdsFor($p);
    $visible = Order::query()->forPortalUser($p)->count();
    $all = Order::query()->count();
    if ($p->hasElevatedAccess()) {
        continue;
    }
    // Чужой заказ не должен проходить forPortalUser
    $foreign = Order::query()
        ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('reference_source_id', $ids->all()))
        ->whereNotNull('reference_source_id')
        ->first();
    if ($foreign) {
        $sneak = Order::query()->forPortalUser($p)->whereKey($foreign->id)->exists();
        if ($sneak) {
            bad("partner {$p->id} sees foreign order {$foreign->id}");
        } else {
            ok("partner {$p->id} blocked foreign order {$foreign->id}");
        }
    }
    ok("partner {$p->id} sources=".$ids->count()." orders_visible=$visible / total=$all");
}

$sample = Order::query()->with('referenceSource', 'source')->whereNotNull('reference_source_id')->latest('id')->first();
if ($sample) {
    $label = OrderSourceFilter::displayName($sample);
    if (str_contains($label, ' — ') || str_starts_with($label, '#')) {
        bad("displayName still has id prefix: $label");
    } else {
        ok("displayName name-only: $label");
    }
    $crm = $sample->referenceSource?->crmId();
    ok("order {$sample->id} crm_source_id=".($crm ?? 'null'));
}

$logs = PartnerSourceAccessLog::query()->count();
ok("access_logs=$logs");

$shared = ReferenceSource::query()->where('shared_with_all_partners', true)->count();
ok("shared_sources=$shared");

echo $fail === 0 ? "ALL_CHECKS_PASSED\n" : "FAILURES=$fail\n";
exit($fail === 0 ? 0 : 1);
