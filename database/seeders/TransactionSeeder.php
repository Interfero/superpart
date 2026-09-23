<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@superpart.ru')->first();

        $orders = Order::where('user_id', $user->id)
            ->where('charge_amount', '>', 0)
            ->orderBy('order_time')
            ->take(10)
            ->get();

        $balanceAfter = 0;
        $amounts = [150, 300, 450, 900, 1340, 2500, 300, 450, 600, 1500];

        foreach ($orders as $index => $order) {
            $amount = $amounts[$index] ?? $order->charge_amount;
            $order->update(['charge_amount' => $amount]);

            $balanceAfter += $amount;

            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'amount' => $amount,
                'operation_type' => 'charge',
                'balance_after' => $balanceAfter,
                'completed_at' => $order->order_time,
            ]);
        }
    }
}
