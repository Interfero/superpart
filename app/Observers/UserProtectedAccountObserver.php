<?php

namespace App\Observers;

use App\Models\User;
use App\Support\ProtectedAccountGuard;

class UserProtectedAccountObserver
{
    public function updating(User $user): void
    {
        ProtectedAccountGuard::assertMayPersist($user, $user->getDirty());
    }

    public function deleting(User $user): void
    {
        $lock = ProtectedAccountGuard::lockFor($user);
        if (! $lock) {
            return;
        }

        if (ProtectedAccountGuard::mutationsAllowedFromCurrentContext()) {
            return;
        }

        throw new \RuntimeException(
            'Учётка #'.$user->id.' защищена agent_account_locks — удаление из консоли/скрипта запрещено.'
        );
    }
}
