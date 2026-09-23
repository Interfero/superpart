<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            WorkTypeSeeder::class,
            UserSeeder::class,
            IntegrationTestPartnerSeeder::class,
            SourceSeeder::class,
            RoleSampleUsersSeeder::class,
            EmployeeSeeder::class,
            OrderSeeder::class,
            TransactionSeeder::class,
            WithdrawalRequestSeeder::class,
            ReviewSeeder::class,
        ]);
    }
}
