<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'in_work',
                'in_work_sd',
                'on_way',
                'clarification',
                'not_processed',
                'waiting',
                'waiting_payment',
                'refusal',
                'refusal_non_profile',
                'cancelled',
                'warranty'
            ) NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('orders')->where('status', 'in_work_sd')->update(['status' => 'in_work']);
        DB::table('orders')->where('status', 'on_way')->update(['status' => 'in_work']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'in_work',
                'clarification',
                'not_processed',
                'waiting',
                'waiting_payment',
                'refusal',
                'refusal_non_profile',
                'cancelled',
                'warranty'
            ) NOT NULL");
        }
    }
};
