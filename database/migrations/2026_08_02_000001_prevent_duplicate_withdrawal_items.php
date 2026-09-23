<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_request_items', function (Blueprint $table) {
            $table->unique('transaction_id', 'withdrawal_items_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_request_items', function (Blueprint $table) {
            $table->dropUnique('withdrawal_items_transaction_unique');
        });
    }
};
