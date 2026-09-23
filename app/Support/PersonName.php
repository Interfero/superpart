<?php

namespace App\Support;

/**
 * ФИО для форм сотрудников: составное поле name и валидация частей.
 */
final class PersonName
{
    public const FIRST_MAX = 20;

    public const LAST_MAX = 30;

    public const MIDDLE_MAX = 30;

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Unique|string>>
     */
    public static function partRules(): array
    {
        $letter = '/^\p{L}+$/u';

        return [
            'last_name' => ['required', 'string', 'max:'.self::LAST_MAX, 'regex:'.$letter],
            'first_name' => ['required', 'string', 'max:'.self::FIRST_MAX, 'regex:'.$letter],
            'middle_name' => ['nullable', 'string', 'max:'.self::MIDDLE_MAX, 'regex:/^(\p{L}+)?$/u'],
        ];
    }

    public static function composeName(string $last, string $first, ?string $middle): string
    {
        $parts = [trim($last), trim($first)];
        $m = trim((string) $middle);
        if ($m !== '') {
            $parts[] = $m;
        }

        return implode(' ', array_filter($parts, fn (string $p) => $p !== ''));
    }

    /**
     * @return array{last_name: string, first_name: string, middle_name: string|null}
     */
    public static function splitLegacyDisplayName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['last_name' => '', 'first_name' => '', 'middle_name' => null];
        }
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 3) {
            return [
                'last_name' => $parts[0],
                'first_name' => $parts[1],
                'middle_name' => implode(' ', array_slice($parts, 2)),
            ];
        }
        if (count($parts) === 2) {
            return ['last_name' => $parts[0], 'first_name' => $parts[1], 'middle_name' => null];
        }

        if (count($parts) === 1) {
            return [
                'last_name' => $parts[0],
                'first_name' => 'Уточните',
                'middle_name' => null,
            ];
        }

        return ['last_name' => '', 'first_name' => '', 'middle_name' => null];
    }
}
