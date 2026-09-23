<?php

namespace App\Support;

use App\Models\AgentAccountLock;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Защита тестовых/важных учёток от правок агентом и one-off PHP-скриптами.
 *
 * Люди через UI (настройки / управление пользователями) могут менять данные.
 * Консоль/скрипты — только с ALLOW_PROTECTED_ACCOUNT_MUTATION=1.
 *
 * Plaintext-пароли в БД не храним.
 */
class ProtectedAccountGuard
{
    /** @var list<string> */
    public const AUTH_FIELDS = [
        'password',
        'email',
    ];

    /** @var list<string> */
    public const PROFILE_FIELDS = [
        'name',
        'last_name',
        'first_name',
        'middle_name',
        'role',
        'parent_user_id',
        'partner_code',
        'api_login',
        'api_password',
        'legal_form',
        'legal_name',
        'inn',
        'ogrn',
        'legal_address',
        // balance НЕ блокируем: вебхуки/cron начислений должны писать users.balance
        'comment',
        'allowed_directions',
    ];

    /** Поля, которые можно менять всегда (логин, тема, леджер). */
    public const ALWAYS_ALLOWED = [
        'last_login_at',
        'theme',
        'remember_token',
        'balance',
        'updated_at',
        'created_at',
    ];

    public static function mutationsAllowedFromCurrentContext(): bool
    {
        if (filter_var((string) env('ALLOW_PROTECTED_ACCOUNT_MUTATION', ''), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        // Вебхуки CRM / внутренние HTTP без сессии пользователя — системный контур.
        if (! app()->runningInConsole()) {
            try {
                $request = request();
                if ($request?->user()) {
                    return true;
                }
                $path = (string) $request?->path();
                if ($path !== '' && str_starts_with($path, 'api/')) {
                    return true;
                }
            } catch (\Throwable) {
                //
            }
        }

        return false;
    }

    public static function lockFor(User $user): ?AgentAccountLock
    {
        if (! $user->exists) {
            return null;
        }

        // Не кэшируем Eloquent-модель (риск Incomplete Class после FTP без dump-autoload).
        $payload = Cache::remember(
            'agent_account_lock:'.$user->id,
            60,
            function () use ($user) {
                $row = AgentAccountLock::query()->where('user_id', $user->id)->first();
                if (! $row) {
                    return null;
                }

                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'lock_auth' => (bool) $row->lock_auth,
                    'lock_profile' => (bool) $row->lock_profile,
                    'reason' => $row->reason,
                ];
            }
        );

        if ($payload === null) {
            return null;
        }

        $lock = new AgentAccountLock;
        $lock->forceFill($payload);
        $lock->exists = true;

        return $lock;
    }

    public static function forgetCache(int $userId): void
    {
        Cache::forget('agent_account_lock:'.$userId);
    }

    /**
     * @param  array<string, mixed>  $dirty
     */
    public static function assertMayPersist(User $user, array $dirty): void
    {
        $lock = static::lockFor($user);
        if (! $lock) {
            return;
        }

        if (static::mutationsAllowedFromCurrentContext()) {
            return;
        }

        $blocked = [];

        if ($lock->lock_auth) {
            foreach (self::AUTH_FIELDS as $field) {
                if (array_key_exists($field, $dirty)) {
                    $blocked[] = $field;
                }
            }
        }

        if ($lock->lock_profile) {
            foreach (self::PROFILE_FIELDS as $field) {
                if (array_key_exists($field, $dirty)) {
                    $blocked[] = $field;
                }
            }
        }

        $blocked = array_values(array_unique($blocked));
        if ($blocked === []) {
            return;
        }

        throw new RuntimeException(
            'Учётка #'.$user->id.' защищена таблицей agent_account_locks. '
            .'Запрещены поля: '.implode(', ', $blocked).'. '
            .'Для аварийной правки из консоли: ALLOW_PROTECTED_ACCOUNT_MUTATION=1. '
            .'Через UI портала правка разрешена.'
        );
    }
}
