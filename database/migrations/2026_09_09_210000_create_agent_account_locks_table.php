<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Отдельная таблица-замок: учётки, которые агент/скрипты не должны менять.
 * Пароли сюда НЕ кладём — только флаги защиты. Секреты остаются хешем в users.password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_account_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('lock_auth')->default(true);
            $table->boolean('lock_profile')->default(true);
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        $emails = [
            'partner@test.local',
            'manager@test.local',
            'teststarsh@test.local',
        ];

        $now = now();
        foreach ($emails as $email) {
            $userId = DB::table('users')->where('email', $email)->value('id');
            if (! $userId) {
                continue;
            }

            DB::table('agent_account_locks')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'lock_auth' => true,
                    'lock_profile' => true,
                    'reason' => 'Тестовая учётка: пароль и профиль не трогать агентом',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_account_locks');
    }
};
