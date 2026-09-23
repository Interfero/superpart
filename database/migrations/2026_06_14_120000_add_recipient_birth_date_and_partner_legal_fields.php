<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_bank_cards', function (Blueprint $table) {
            $table->date('recipient_birth_date')->nullable()->after('recipient');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('legal_form', 8)->nullable()->after('comment');
            $table->string('legal_name', 255)->nullable()->after('legal_form');
            $table->string('inn', 12)->nullable()->after('legal_name');
            $table->string('ogrn', 15)->nullable()->after('inn');
            $table->text('legal_address')->nullable()->after('ogrn');
        });

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->date('recipient_birth_date')->nullable()->after('bank_card');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn('recipient_birth_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['legal_form', 'legal_name', 'inn', 'ogrn', 'legal_address']);
        });

        Schema::table('partner_bank_cards', function (Blueprint $table) {
            $table->dropColumn('recipient_birth_date');
        });
    }
};
