<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@superpart.ru')->first();

        $sources = [
            ['name' => 'Иван only НСК1', 'comment' => '9086418214', 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Сергей Тверь', 'comment' => '9085638835', 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Морозов Евгений', 'comment' => null, 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Логинов Сергей', 'comment' => null, 'source_type' => Source::TYPE_OTHER],
            ['name' => 'Лосо Авито', 'comment' => null, 'source_type' => Source::TYPE_ADVERTISING_PLATFORM],
        ];

        foreach ($sources as $data) {
            Source::updateOrCreate(
                ['user_id' => $user->id, 'name' => $data['name']],
                [
                    'comment' => $data['comment'],
                    'source_type' => $data['source_type'],
                ]
            );
        }
    }
}
