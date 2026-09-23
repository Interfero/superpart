<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_bank_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('card_number', 16);
            $table->string('bank', 20);
            $table->string('recipient', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_bank_cards');
    }
};
