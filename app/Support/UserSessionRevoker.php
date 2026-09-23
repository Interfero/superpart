<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Завершение всех сессий пользователя (после сброса пароля и т.п.).
 * Полное удаление строк в `sessions` работает при session driver = database.
 */
final class UserSessionRevoker
{
    public static function revokeAll(User $user): void
    {
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $user->setRememberToken(Str::random(60));
    }
}
