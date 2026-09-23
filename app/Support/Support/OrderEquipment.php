<?php

namespace App\Support;

/**
 * Виды техники и core/non_core — как в CRM (Order::EQUIPMENT_TYPES / CORE_EQUIPMENT).
 */
class OrderEquipment
{
    public const EQUIPMENT_TYPES = [
        'tv' => 'ЖК ТВ/Плазменные ТВ/Кинескопные ТВ',
        'computer' => 'Компьютер/Ноутбук/Моноблок/Отдельные комплектующие от ПК',
        'printer' => 'Принтер/МФУ',
        'monitor' => 'Мониторы/Видеокарты (Отдельно от ПК)',
        'boiler' => 'Бойлеры',
        'oven' => 'Духовки/Духовые шкафы/Электроплиты',
        'conditioner' => 'Кондиционеры/Сплит-системы',
        'coffee_machine' => 'Кофемашины',
        'dishwasher' => 'Посудомоечные машины',
        'washing_machine' => 'Стиральные/Сушильные машины',
        'fridge' => 'Холодильники/Морозилки',
        'game_console' => 'PlayStation/XBOX/Nintendo switch/Steam deck',
        'data_recovery' => 'Восстановление информации с любого носителя устройства',
        'cable' => 'Обжим интернет кабеля',
        'router' => 'Роутер',
        'smartphone' => 'Смартфоны/Планшеты',
        'other_device' => 'Прочая',
    ];

    public const CORE_EQUIPMENT = ['tv', 'computer', 'printer', 'monitor'];

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
            'computer_help' => [
                'tv', 'computer', 'printer', 'monitor', 'router',
                'game_console', 'data_recovery', 'cable', 'smartphone', 'other_device',
            ],
            'appliance_repair' => [
                'boiler', 'oven', 'conditioner', 'coffee_machine',
                'dishwasher', 'washing_machine', 'fridge',
            ],
            'handyman' => [
                'cable', 'router', 'other_device',
            ],
            default => self::codes(),
        };
    }
}
