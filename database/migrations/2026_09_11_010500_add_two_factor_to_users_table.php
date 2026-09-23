<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->string('two_factor_secret', 64)->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('users', 'two_factor_secret')) {
                $cols[] = 'two_factor_secret';
            }
            if (Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $cols[] = 'two_factor_confirmed_at';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
