<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Данные для ручного теста интеграции SuperPart ↔ Levelion CRM.
 *
 * В CRM в поле «ID партнёра (SuperPart)» подставляют user_id созданного пользователя
 * (смотреть в БД или после сида в выводе при необходимости).
 */
class IntegrationTestPartnerSeeder extends Seeder
{
    public const LEVELION_CITY_ID = 49;

    public function run(): void
    {
        City::updateOrCreate(
            ['levelion_city_id' => self::LEVELION_CITY_ID],
            [
                'name' => 'Тестоград',
                'is_available' => true,
                'load_percentage' => 0,
            ]
        );

        $user = User::updateOrCreate(
            ['email' => 'testislav.partner@superpart.ru'],
            [
                'name' => 'Тестислав Партнёров',
                'password' => Hash::make('password'),
                'balance' => 0,
                'theme' => 'dark',
                'email_verified_at' => now(),
                'role' => User::ROLE_PARTNER,
                'parent_user_id' => null,
            ]
        );

        $city = City::query()->where('levelion_city_id', self::LEVELION_CITY_ID)->first();
        if ($city) {
            $user->allowedCities()->sync([$city->id]);
        }
    }
}
