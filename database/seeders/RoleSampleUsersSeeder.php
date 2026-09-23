<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * По одному дополнительному пользователю на каждую роль (для проверки прав и UI).
 * Пароль у всех: password (как в UserSeeder).
 *
 * Менеджер привязан к партнёру из этого же сида; источники — как в SourceSeeder, затем pivot менеджера к источникам партнёра.
 */
class RoleSampleUsersSeeder extends Seeder
{
    public function run(): void
    {
        $cityIds = City::query()->pluck('id')->all();

        $developer = User::updateOrCreate(
            ['email' => 'roles-sample-developer@superpart.ru'],
            [
                'name' => 'Сэмпл Разработчик',
                'password' => Hash::make('password'),
                'balance' => 0,
                'theme' => 'dark',
                'email_verified_at' => now(),
                'role' => User::ROLE_DEVELOPER,
                'parent_user_id' => null,
            ]
        );

        $partner = User::updateOrCreate(
            ['email' => 'roles-sample-partner@superpart.ru'],
            [
                'name' => 'Сэмпл Партнёр',
                'password' => Hash::make('password'),
                'balance' => 0,
                'theme' => 'dark',
                'email_verified_at' => now(),
                'role' => User::ROLE_PARTNER,
                'parent_user_id' => null,
            ]
        );

        if ($cityIds !== []) {
            $partner->allowedCities()->sync($cityIds);
        }

        $manager = User::updateOrCreate(
            ['email' => 'roles-sample-manager@superpart.ru'],
            [
                'name' => 'Сэмпл Менеджер',
                'password' => Hash::make('password'),
                'balance' => 0,
                'theme' => 'dark',
                'email_verified_at' => now(),
                'role' => User::ROLE_MANAGER,
                'parent_user_id' => $partner->id,
            ]
        );

        if ($cityIds !== []) {
            $manager->allowedCities()->sync($cityIds);
        }

        $this->seedSourcesLikeSourceSeeder($developer);
        $this->seedSourcesLikeSourceSeeder($partner);
        $this->seedSourcesLikeSourceSeeder($manager);

        $partnerSourceIds = Source::query()
            ->where('user_id', $partner->id)
            ->pluck('id')
            ->all();

        if ($partnerSourceIds !== []) {
            $manager->allowedSources()->sync($partnerSourceIds);
            $manager->allowedReferenceSources()->detach();
        }
    }

    private function seedSourcesLikeSourceSeeder(User $user): void
    {
        $templates = [
            ['name' => 'Иван only НСК1', 'comment' => '9086418214', 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Сергей Тверь', 'comment' => '9085638835', 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Морозов Евгений', 'comment' => null, 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Логинов Сергей', 'comment' => null, 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Лосо Авито', 'comment' => null, 'source_type' => Source::TYPE_ADVERTISING_PLATFORM],
        ];

        $prefix = strtok($user->email, '@');
        foreach ($templates as $tpl) {
            $name = $tpl['name'].' ('.$prefix.')';

            Source::updateOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                [
                    'comment' => $tpl['comment'],
                    'source_type' => $tpl['source_type'],
                ]
            );
        }
    }
}
