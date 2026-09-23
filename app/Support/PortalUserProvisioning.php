<?php

namespace App\Support;

/**
 * Создание пользователей портала: суффикс почты и генерация пароля (как в HR Lead Control).
 */
final class PortalUserProvisioning
{
    public const EMAIL_SUFFIX = '@sp.ru';

    public const PASSWORD_MAX_LENGTH = 10;

    /** Длина автоматически сгенерированного пароля. */
    public const PASSWORD_GENERATED_LENGTH = 10;

    public static function generatePassword(int $length = self::PASSWORD_GENERATED_LENGTH): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    public static function normalizeLocalPart(?string $input): string
    {
        $t = trim((string) $input);
        if ($t === '') {
            return '';
        }
        if (str_contains($t, '@')) {
            $local = trim(explode('@', $t, 2)[0]);

            return $local;
        }

        return $t;
    }

    public static function emailFromLocalPart(string $localPart): string
    {
        $local = strtolower(self::normalizeLocalPart($localPart));

        return $local.self::EMAIL_SUFFIX;
    }

    public static function localPartFromEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }
        $suffix = preg_quote(self::EMAIL_SUFFIX, '/');
        $out = preg_replace('/'.$suffix.'$/i', '', $email);

        return is_string($out) ? $out : $email;
    }

    public static function isSpDomainEmail(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return str_ends_with(strtolower($email), strtolower(self::EMAIL_SUFFIX));
    }
}
