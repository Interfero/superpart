<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'test@superpart.ru',
                'name' => 'Животков Иван',
                'balance' => 78250.00,
                'role' => User::ROLE_PARTNER,
            ],
            [
                'email' => 'admin@superpart.ru',
                'name' => 'Петров Сергей',
                'balance' => 50000.00,
                'role' => User::ROLE_DEVELOPER,
            ],
            [
                'email' => 'partner1@superpart.ru',
                'name' => 'Смирнова Анна',
                'balance' => 12500.50,
                'role' => User::ROLE_PARTNER,
            ],
            [
                'email' => 'partner2@superpart.ru',
                'name' => 'Крылов Дмитрий',
                'balance' => 34000.00,
                'role' => User::ROLE_PARTNER,
            ],
            [
                'email' => 'partner3@superpart.ru',
                'name' => 'Орлова Екатерина',
                'balance' => 8900.25,
                'role' => User::ROLE_PARTNER,
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'balance' => $data['balance'],
                    'theme' => 'dark',
                    'email_verified_at' => now(),
                    'role' => $role,
                    'parent_user_id' => null,
                ]
            );

            if ($role === User::ROLE_PARTNER) {
                $user->allowedCities()->sync(City::query()->pluck('id')->all());
            }
        }
    }
}
