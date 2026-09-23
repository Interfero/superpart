<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::query()->orderBy('id')->get(['id', 'email', 'role', 'parent_user_id']) as $user) {
    echo "{$user->id}\t{$user->email}\t{$user->role}\tparent={$user->parent_user_id}\n";
}
