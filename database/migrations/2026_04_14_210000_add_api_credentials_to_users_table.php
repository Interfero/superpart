<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_login')->nullable()->after('theme');
            $table->text('api_password')->nullable()->after('api_login');
            $table->string('partner_code')->nullable()->after('api_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['api_login', 'api_password', 'partner_code']);
        });
    }
};
