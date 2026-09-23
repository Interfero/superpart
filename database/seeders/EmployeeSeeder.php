<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@superpart.ru')->first();

        $employees = [
            ['name' => 'Животносов Иван', 'email' => 'braxton13@k.c', 'status' => 'active', 'last_visit_at' => '2026-02-20 14:30:00'],
            ['name' => 'Логинов Сергей Романович', 'email' => 'Crash293009@yandex.ru', 'status' => 'active', 'last_visit_at' => '2026-03-15 10:00:00'],
            ['name' => 'Морозов Евгений Андреевич', 'email' => 'zhenya25197@gmail.com', 'status' => 'active', 'last_visit_at' => '2026-03-20 09:15:00'],
            ['name' => 'Алиев Иса Лукманович', 'email' => 'prn.2054@yandex.ru', 'status' => 'active', 'last_visit_at' => '2026-03-28 16:45:00'],
        ];

        foreach ($employees as $data) {
            Employee::updateOrCreate(
                ['user_id' => $user->id, 'email' => $data['email']],
                [
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'last_visit_at' => $data['last_visit_at'],
                ]
            );
        }
    }
}
