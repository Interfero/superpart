<?php

namespace Database\Seeders;

use App\Support\WorkTypeCatalog;
use Illuminate\Database\Seeder;

class WorkTypeSeeder extends Seeder
{
    public function run(): void
    {
        WorkTypeCatalog::sync();
    }
}
