<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalRequestItem;
use Illuminate\Database\Seeder;

class WithdrawalRequestSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@superpart.ru')->first();
        $transactions = Transaction::where('user_id', $user->id)
            ->where('operation_type', 'charge')
            ->orderBy('completed_at')
            ->take(4)
            ->get();

        if ($transactions->count() < 2) {
            return;
        }

        $firstBatch = $transactions->take(2);
        $totalAmount1 = $firstBatch->sum('amount');

        $withdrawal1 = WithdrawalRequest::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total_amount' => $totalAmount1,
            'bank_card' => '**** **** **** 4532',
            'requisites' => "Животков Иван Сергеевич\nСбербанк\nР/С 40817810938000123456",
            'comment' => 'Вывод за январь',
        ]);

        foreach ($firstBatch as $transaction) {
            WithdrawalRequestItem::create([
                'withdrawal_request_id' => $withdrawal1->id,
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->amount,
                'city_id' => Order::find($transaction->order_id)?->city_id ?? City::first()->id,
                'status' => 'Ожидает выплаты',
                'created_local' => $transaction->completed_at,
            ]);
        }

        $secondBatch = $transactions->slice(2);
        if ($secondBatch->isEmpty()) {
            return;
        }

        $totalAmount2 = $secondBatch->sum('amount');

        $withdrawal2 = WithdrawalRequest::create([
            'user_id' => $user->id,
            'status' => 'in_work',
            'total_amount' => $totalAmount2,
            'bank_card' => '**** **** **** 7891',
            'requisites' => "Животков Иван Сергеевич\nТинькофф\nР/С 40817810100000567890",
            'comment' => null,
        ]);

        foreach ($secondBatch as $transaction) {
            WithdrawalRequestItem::create([
                'withdrawal_request_id' => $withdrawal2->id,
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->amount,
                'city_id' => Order::find($transaction->order_id)?->city_id ?? City::first()->id,
                'status' => 'Ожидает выплаты',
                'created_local' => $transaction->completed_at,
            ]);
        }
    }
}
