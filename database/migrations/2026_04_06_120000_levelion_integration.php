<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('levelion_city_id')->nullable()->unique()->after('id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('street')->nullable()->after('address');
            $table->string('house')->nullable()->after('street');
            $table->string('flat', 64)->nullable()->after('house');
            $table->text('address_adds')->nullable()->after('flat');
            $table->text('order_adds')->nullable()->after('address_adds');
            $table->unsignedBigInteger('levelion_order_id')->nullable()->after('order_adds');
            $table->string('sync_status', 32)->default('pending')->after('levelion_order_id');
            $table->string('equipment_type', 64)->nullable()->after('work_type_id');
            $table->string('order_core', 32)->nullable()->after('equipment_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('levelion_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['levelion_order_id']);
            $table->dropColumn([
                'street',
                'house',
                'flat',
                'address_adds',
                'order_adds',
                'levelion_order_id',
                'sync_status',
                'equipment_type',
                'order_core',
            ]);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('levelion_city_id');
        });
    }
};
