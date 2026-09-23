<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('client_age')->nullable()->after('client_phone');
        });

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

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('client_age');
        });

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
                'cancelled'
            ) NOT NULL");
        }
    }
};
