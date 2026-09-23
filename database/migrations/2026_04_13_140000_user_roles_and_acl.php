<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('partner')->after('password');
            $table->foreignId('parent_user_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->where('email', 'admin@superpart.ru')->update(['role' => 'developer']);

        Schema::create('user_allowed_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'city_id']);
        });

        Schema::create('user_allowed_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_allowed_sources');
        Schema::dropIfExists('user_allowed_cities');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropColumn(['parent_user_id', 'role']);
        });
    }
};
