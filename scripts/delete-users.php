<?php

/**
 * Одноразовое удаление тестовых учёток (запуск на сервере из корня проекта).
 * php scripts/delete-users.php 3 4 5
 */

use App\Models\User;
use App\Support\UserSessionRevoker;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ids = array_slice($argv, 1);

if ($ids === []) {
    fwrite(STDERR, "Usage: php scripts/delete-users.php <id>...\n");
    exit(1);
}

foreach ($ids as $rawId) {
    $id = (int) $rawId;
    $user = User::query()->find($id);

    if (! $user) {
        echo "ID {$id}: not found\n";
        continue;
    }

    echo "ID {$id}: {$user->email} ({$user->role})\n";

    if ($user->managedUsers()->exists()) {
        echo "  skip: has managed users\n";
        continue;
    }

    if ($user->orders()->exists()) {
        echo "  skip: has orders\n";
        continue;
    }

    if ($user->sources()->exists()) {
        echo "  skip: has sources\n";
        continue;
    }

    if (
        $user->transactions()->exists()
        || $user->reviews()->exists()
        || $user->withdrawalRequests()->exists()
        || $user->employees()->exists()
    ) {
        echo "  skip: has related records\n";
        continue;
    }

    $user->allowedCities()->detach();
    $user->allowedSources()->detach();
    $user->allowedReferenceSources()->detach();
    UserSessionRevoker::revokeAll($user);
    $user->delete();

    echo "  deleted\n";
}
