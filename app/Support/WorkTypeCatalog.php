<?php

namespace App\Support;

use App\Models\WorkType;

/**
 * Справочник «Виды работ» — сводная таблица регламента КП (10 строк).
 */
class WorkTypeCatalog
{
    /** @var array<string, list<string>> */
    private const LEGACY_NAMES_BY_CODE = [
        'game_console' => [
            'PlayStation/XBOX/Nintendo switch/Steam deck',
            'Чистка и настройка PS/Xbox/Nintendo switch/Steam Deck',
        ],
        'data_recovery' => [
            'Восстановление информации с любого носителя устройства',
            'Восстановление данных с носителей',
        ],
        'tv' => [
            'ЖК ТВ/Плазменные ТВ/Кинескопные ТВ',
            'ЖК ТВ/Плазменные ТВ/ Кинескопные ТВ',
            'Телевизоры',
        ],
        'computer' => [
            'Компьютер/Ноутбук/Моноблок/Отдельные комплектующие от ПК',
            'Компьютер/Ноутбук/Моноблок/ Отдельные комплектующие от ПК',
            'Пк/Моноблоки/Ноутбуки',
        ],
        'monitor' => [
            'Мониторы/Видеокарты (Отдельно от ПК)',
            'Мониторы/Видеокарты (без ПК)',
        ],
        'cable' => [
            'Обжим интернет кабеля',
            'Обжим кабеля без пк',
        ],
        'printer' => [
            'Принтер/МФУ',
            'Ремонт принтера/Настройка без пк/Прошивка принтера',
        ],
        'other_device' => [
            'Прочее',
            'Тип техники «ПРОЧАЯ»',
        ],
        'router' => [
            'Роутер',
            'Роутеры',
        ],
        'smartphone' => [
            'Смартфоны/Планшеты',
            'Телефоны/Планшеты',
        ],
    ];

    /**
     * @return list<array{
     *     equipment_code: string,
     *     sort_order: int,
     *     name: string,
     *     description: string,
     *     is_profile: bool,
     *     legacy_names: list<string>
     * }>
     */
    public static function rows(): array
    {
        $out = [];

        foreach (OrderEquipmentRegulation::COMPUTER_HELP_ORDER as $index => $code) {
            $regulation = OrderEquipmentRegulation::get($code);
            if ($regulation === null) {
                continue;
            }

            $legacy = self::LEGACY_NAMES_BY_CODE[$code] ?? [];
            $legacy = array_values(array_unique([...$legacy, $regulation['label']]));

            $out[] = [
                'equipment_code' => $code,
                'sort_order' => $index + 1,
                'name' => $regulation['label'],
                'description' => $regulation['description'],
                'is_profile' => $regulation['profile'],
                'legacy_names' => $legacy,
            ];
        }

        return $out;
    }

    /** Синхронизация таблицы work_types с регламентом КП. */
    public static function sync(): void
    {
        $keptIds = [];

        foreach (self::rows() as $row) {
            $legacyNames = $row['legacy_names'];
            unset($row['legacy_names']);

            $model = WorkType::query()
                ->where('equipment_code', $row['equipment_code'])
                ->orWhereIn('name', $legacyNames)
                ->first();

            if ($model) {
                $model->update($row);
            } else {
                $model = WorkType::create($row);
            }

            $keptIds[] = $model->id;
        }

        WorkType::query()
            ->whereNotIn('id', $keptIds)
            ->whereDoesntHave('orders')
            ->delete();
    }
}
