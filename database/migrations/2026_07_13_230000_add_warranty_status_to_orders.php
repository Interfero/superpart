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

        // Закрытые гарантии раньше попадали в «Отказ» (completed без суммы).
        DB::table('orders')
            ->where('type', 'warranty')
            ->whereIn('status', ['refusal', 'refusal_non_profile'])
            ->update([
                'status' => 'warranty',
                'charge_amount' => null,
            ]);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('orders')
            ->where('status', 'warranty')
            ->update(['status' => 'refusal']);

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
};
