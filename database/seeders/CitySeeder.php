<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'Абакан',
            'Адлер (Сочи)',
            'Анапа',
            'Ангарск (Иркутск)',
            'Армавир',
            'Артём (Владивосток)',
            'Астрахань',
            'Ачинск (Красноярск)',
            'Балаково',
            'Барнаул',
            'Белгород',
            'Бердск (Новосибирск)',
            'Березники (Пермь)',
            'Бийск',
            'Благовещенск',
            'Братск',
            'Брянск',
            'Великий Новгород',
            'Владивосток',
            'Волгоград',
            'Воронеж',
            'Екатеринбург',
            'Иркутск',
            'Казань',
            'Калининград',
            'Кемерово',
            'Красноярск',
            'Новокузнецк',
            'Новосибирск',
            'Омск',
        ];

        foreach ($cities as $name) {
            City::updateOrCreate(
                ['name' => $name],
                [
                    'is_available' => true,
                    'load_percentage' => round(mt_rand(30, 300) / 10, 1),
                ]
            );
        }
    }
}
