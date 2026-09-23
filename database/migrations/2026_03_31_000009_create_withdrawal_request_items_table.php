<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->foreignId('city_id')->constrained();
            $table->string('status', 50);
            $table->dateTime('created_local');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_request_items');
    }
};
