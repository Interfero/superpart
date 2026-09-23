<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // После бага синхронизации флаг сбрасывался в false, хотя CRM не присылает поле.
        DB::table('reference_sources')
            ->where('available_for_superpart', false)
            ->update(['available_for_superpart' => true]);
    }

    public function down(): void
    {
        // Не восстанавливаем прежние значения — данных нет.
    }
};
