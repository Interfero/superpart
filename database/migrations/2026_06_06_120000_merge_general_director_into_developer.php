<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'general_director')
            ->update(['role' => 'developer']);
    }

    public function down(): void
    {
        // Не восстанавливаем роль general_director — она снята с проекта.
    }
};
