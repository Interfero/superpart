<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Общие источники (без закрепления за партнёром) были обнулены при первой синхронизации
        // без поля available_for_superpart в ответе CRM.
        DB::table('reference_sources')
            ->whereNull('superpart_partner_id')
            ->whereNotNull('levelion_source_id')
            ->update(['available_for_superpart' => true]);
    }

    public function down(): void
    {
        // Откат не требуется: флаг управляется CRM при явной передаче available_for_superpart.
    }
};
