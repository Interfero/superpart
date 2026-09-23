<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@superpart.ru')->first();
        $orders = Order::where('user_id', $user->id)->take(3)->get();
        $cityIds = City::pluck('id')->toArray();

        if ($orders->isEmpty()) {
            return;
        }

        $reviews = [
            [
                'order_id' => $orders[0]->id,
                'status' => 'new',
                'review_url' => 'https://www.avito.ru/review/seller/bad_service_12345',
                'review_type' => 'Авито',
                'city_id' => $orders[0]->city_id ?? $cityIds[0],
                'closed_at' => null,
            ],
            [
                'order_id' => $orders[1]->id ?? $orders[0]->id,
                'status' => 'resolved',
                'review_url' => 'https://t.me/reviews_channel/567',
                'review_type' => 'Telegram',
                'city_id' => $orders[1]->city_id ?? $cityIds[1] ?? $cityIds[0],
                'closed_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
            ],
            [
                'order_id' => $orders[2]->id ?? $orders[0]->id,
                'status' => 'not_resolved',
                'review_url' => 'https://yandex.ru/maps/reviews/org/123456789',
                'review_type' => 'Яндекс Карты',
                'city_id' => $orders[2]->city_id ?? $cityIds[2] ?? $cityIds[0],
                'closed_at' => null,
            ],
        ];

        foreach ($reviews as $data) {
            Review::create(array_merge($data, ['user_id' => $user->id]));
        }
    }
}
