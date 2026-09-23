<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('orders')->where('status', 'callback')->update(['status' => 'clarification']);
        DB::table('orders')->where('status', 'pending')->update(['status' => 'waiting']);
        DB::table('orders')->whereIn('status', ['cancelled_cc', 'cancelled_city'])->update(['status' => 'cancelled']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'in_work',
                'clarification',
                'not_processed',
                'waiting',
                'waiting_payment',
                'refusal',
                'refusal_non_profile',
                'cancelled'
            ) NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

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
                'callback',
                'pending',
                'cancelled_cc',
                'cancelled_city'
            ) NOT NULL");
        }
    }
};
