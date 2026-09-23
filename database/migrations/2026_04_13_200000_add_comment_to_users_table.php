<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'comment')) {
            return;
        }

        if (Schema::hasColumn('users', 'parent_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('comment')->nullable()->after('parent_user_id');
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->text('comment')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
