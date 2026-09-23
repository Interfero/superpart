<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained();
            $table->enum('status', [
                'in_work',
                'clarification',
                'not_processed',
                'waiting',
                'waiting_payment',
                'refusal',
                'refusal_non_profile',
                'cancelled',
            ]);
            $table->enum('type', ['first_time', 'warranty', 'repeat']);
            $table->foreignId('source_id')->nullable()->constrained();
            $table->foreignId('work_type_id')->nullable()->constrained();
            $table->dateTime('order_time');
            $table->string('client_name');
            $table->string('client_phone', 50);
            $table->boolean('is_non_profile')->default(false);
            $table->string('settlement')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->decimal('charge_amount', 10, 2)->nullable();
            $table->dateTime('created_local');
            $table->dateTime('closed_local')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
