<?php

/**
 * Принудительное удаление тестовых учёток с очисткой связей.
 * php scripts/force-delete-users.php 3 4 5
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ids = array_map('intval', array_slice($argv, 1));

if ($ids === []) {
    fwrite(STDERR, "Usage: php scripts/force-delete-users.php <id>...\n");
    exit(1);
}

// Сначала менеджеров и дочерние учётки, потом партнёров/прочих.
rsort($ids);

foreach ($ids as $id) {
    $user = User::query()->find($id);

    if (! $user) {
        echo "ID {$id}: not found\n";
        continue;
    }

    echo "ID {$id}: {$user->email} ({$user->role})\n";

    foreach ($user->managedUsers()->orderByDesc('id')->get() as $child) {
        echo "  delete managed user #{$child->id} {$child->email}\n";
        purgeUser($child);
    }

    purgeUser($user);
    echo "  deleted\n";
}

function purgeUser(User $user): void
{
    $id = (int) $user->id;
    $db = \Illuminate\Support\Facades\DB::connection();

    $db->table('sessions')->where('user_id', $id)->delete();
    $db->table('user_allowed_cities')->where('user_id', $id)->delete();
    $db->table('user_allowed_sources')->where('user_id', $id)->delete();
    $db->table('user_allowed_reference_sources')->where('user_id', $id)->delete();
    $db->table('partner_bank_cards')->where('user_id', $id)->delete();
    $db->table('partner_phones')->where('user_id', $id)->delete();
    $db->table('withdrawal_requests')->where('user_id', $id)->delete();
    $db->table('transactions')->where('user_id', $id)->delete();
    $db->table('reviews')->where('user_id', $id)->delete();
    $db->table('employees')->where('user_id', $id)->delete();
    $db->table('sources')->where('user_id', $id)->delete();
    $db->table('orders')->where('user_id', $id)->delete();

    $db->table('users')->where('parent_user_id', $id)->update(['parent_user_id' => null]);

    $db->table('users')->where('id', $id)->delete();
}
