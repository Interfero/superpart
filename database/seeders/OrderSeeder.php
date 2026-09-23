<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Source;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@superpart.ru')->first();
        $cityIds = City::pluck('id')->toArray();
        $sourceIds = Source::where('user_id', $user->id)->pluck('id')->toArray();
        $employeeIds = Employee::where('user_id', $user->id)->pluck('id')->toArray();
        $workTypes = WorkType::all();
        $workTypeIds = $workTypes->pluck('id')->toArray();
        $nonProfileWorkTypeIds = $workTypes->where('is_profile', false)->pluck('id')->toArray();
        $profileWorkTypeId = $workTypes->where('is_profile', true)->first()?->id;

        $statuses = ['in_work', 'clarification', 'not_processed', 'waiting', 'waiting_payment', 'refusal', 'refusal_non_profile', 'cancelled'];
        $types = ['first_time', 'warranty', 'repeat'];

        $clients = [
            ['name' => 'Иванов Алексей', 'phone' => '+7 928-525-2808'],
            ['name' => 'Петрова Мария', 'phone' => '+7 913-456-7890'],
            ['name' => 'Сидоров Дмитрий', 'phone' => '+7 905-123-4567'],
            ['name' => 'Козлова Анна', 'phone' => '+7 999-876-5432'],
            ['name' => 'Николаев Павел', 'phone' => '+7 950-321-6789'],
            ['name' => 'Фёдорова Елена', 'phone' => '+7 961-234-5678'],
            ['name' => 'Морозов Артём', 'phone' => '+7 923-567-8901'],
            ['name' => 'Васильева Ольга', 'phone' => '+7 918-765-4321'],
            ['name' => 'Кузнецов Роман', 'phone' => '+7 904-111-2233'],
            ['name' => 'Соколова Татьяна', 'phone' => '+7 983-444-5566'],
            ['name' => 'Попов Игорь', 'phone' => '+7 915-222-3344'],
            ['name' => 'Лебедева Дарья', 'phone' => '+7 962-888-9900'],
            ['name' => 'Новиков Андрей', 'phone' => '+7 908-333-4455'],
            ['name' => 'Волкова Наталья', 'phone' => '+7 977-666-7788'],
            ['name' => 'Зайцев Максим', 'phone' => '+7 916-555-6677'],
        ];

        $settlements = [
            'ул. Ленина, д. 15', 'пр. Мира, д. 42', 'ул. Советская, д. 7',
            'ул. Гагарина, д. 23', 'пр. Победы, д. 1', 'ул. Кирова, д. 88',
            'ул. Пушкина, д. 3', 'пр. Строителей, д. 55', null, null,
        ];

        $chargeAmounts = [150, 300, 450, 600, 900, 1340, 1500, 2500, 3000, 0];

        $orders = [];

        for ($i = 0; $i < 25; $i++) {
            $status = $statuses[$i % count($statuses)];
            $type = $types[$i % count($types)];
            $client = $clients[$i % count($clients)];
            $workTypeId = $workTypeIds[array_rand($workTypeIds)];
            $isNonProfile = in_array($workTypeId, $nonProfileWorkTypeIds);

            $orderDate = now()->subDays(rand(0, 60));
            $chargeAmount = in_array($status, ['waiting_payment', 'in_work', 'waiting'])
                ? $chargeAmounts[array_rand($chargeAmounts)]
                : ($status === 'refusal' || $status === 'refusal_non_profile' || $status === 'cancelled' ? 0 : $chargeAmounts[array_rand($chargeAmounts)]);

            $closedLocal = in_array($status, ['refusal', 'refusal_non_profile', 'cancelled'])
                ? $orderDate->copy()->addDays(rand(1, 5))
                : null;

            $orders[] = [
                'user_id' => $user->id,
                'city_id' => $cityIds[array_rand($cityIds)],
                'status' => $status,
                'type' => $type,
                'source_id' => $sourceIds[array_rand($sourceIds)],
                'work_type_id' => $workTypeId,
                'order_time' => $orderDate->format('Y-m-d H:i:s'),
                'client_name' => $client['name'],
                'client_phone' => $client['phone'],
                'is_non_profile' => $isNonProfile,
                'settlement' => $settlements[array_rand($settlements)],
                'address' => null,
                'employee_id' => rand(0, 4) > 0 ? $employeeIds[array_rand($employeeIds)] : null,
                'charge_amount' => $chargeAmount,
                'created_local' => $orderDate->format('Y-m-d H:i:s'),
                'closed_local' => $closedLocal?->format('Y-m-d H:i:s'),
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ];
        }

        foreach ($orders as $data) {
            Order::create($data);
        }
    }
}
