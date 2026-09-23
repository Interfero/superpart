<?php

namespace App\Support;

/**
 * Виды работ (типы техники) и профильность — регламент КП.
 */
class OrderEquipment
{
    /** @var array<string, string> */
    public const EQUIPMENT_TYPES = [
        'data_recovery' => 'Восстановление данных с носителей',
        'computer' => 'Пк/Моноблоки/Ноутбуки',
        'monitor' => 'Мониторы/Видеокарты (без ПК)',
        'cable' => 'Обжим кабеля без пк',
        'router' => 'Роутеры',
        'printer' => 'Ремонт принтера/Настройка без пк/Прошивка принтера',
        'tv' => 'Телевизоры',
        'smartphone' => 'Телефоны/Планшеты',
        'game_console' => 'Чистка и настройка PS/Xbox/Nintendo switch/Steam Deck',
        'other_device' => 'Тип техники «ПРОЧАЯ»',
        'boiler' => 'Бойлеры',
        'oven' => 'Духовки/Духовые шкафы/Электроплиты',
        'conditioner' => 'Кондиционеры/Сплит-системы',
        'coffee_machine' => 'Кофемашины',
        'dishwasher' => 'Посудомоечные машины',
        'washing_machine' => 'Стиральные/Сушильные машины',
        'fridge' => 'Холодильники/Морозилки',
    ];

    public const CORE_EQUIPMENT = ['computer'];

    public static function orderCoreFromEquipment(string $equipmentType): string
    {
        return in_array($equipmentType, self::CORE_EQUIPMENT, true) ? 'core' : 'non_core';
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::EQUIPMENT_TYPES);
    }

    /** @return list<string> */
    public static function codesForServerType(?string $serverType): array
    {
        return match ($serverType) {
            'computer_help' => OrderEquipmentRegulation::COMPUTER_HELP_ORDER,
            'appliance_repair' => [
                'boiler', 'oven', 'conditioner', 'coffee_machine',
                'dishwasher', 'washing_machine', 'fridge',
                'tv',
            ],
            'handyman' => [
                'cable', 'router', 'other_device',
                'tv',
            ],
            default => [],
        };
    }

    public static function label(string $code): string
    {
        $regulation = OrderEquipmentRegulation::get($code);

        return $regulation['label'] ?? (self::EQUIPMENT_TYPES[$code] ?? $code);
    }

    /**
     * Каталог для формы заявки: порядок и подписи (правила — в справочнике «Виды работ»).
     *
     * @param  list<array<string, mixed>>  $fromApi
     * @return list<array{code: string, label: string}>
     */
    public static function catalogForOrderForm(array $fromApi = []): array
    {
        $byCode = collect($fromApi)->keyBy(fn ($row) => (string) ($row['code'] ?? ''));

        $orderedCodes = array_merge(
            OrderEquipmentRegulation::COMPUTER_HELP_ORDER,
            array_values(array_diff(self::codes(), OrderEquipmentRegulation::COMPUTER_HELP_ORDER))
        );

        $out = [];

        foreach ($orderedCodes as $code) {
            $regulation = OrderEquipmentRegulation::get($code);
            $apiRow = $byCode->get($code);
            $defaultLabel = self::EQUIPMENT_TYPES[$code] ?? $code;

            $out[] = [
                'code' => $code,
                'label' => is_array($apiRow) && ! empty($apiRow['label'])
                    ? (string) $apiRow['label']
                    : ($regulation['label'] ?? $defaultLabel),
            ];
        }

        return $out;
    }
}
