<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('orders')->whereNotNull('charge_amount')->update([
                'charge_amount' => DB::raw('ROUND(charge_amount, 0)'),
            ]);

            DB::table('transactions')->update([
                'amount' => DB::raw('ROUND(amount, 0)'),
            ]);

            DB::table('withdrawal_request_items')->update([
                'amount' => DB::raw('ROUND(amount, 0)'),
            ]);

            $withdrawals = DB::table('withdrawal_requests')->orderBy('id')->get(['id', 'total_amount', 'ledger_transaction_id']);

            foreach ($withdrawals as $withdrawal) {
                $itemsQuery = DB::table('withdrawal_request_items')
                    ->where('withdrawal_request_id', $withdrawal->id);

                $total = (clone $itemsQuery)->exists()
                    ? round((float) (clone $itemsQuery)->sum('amount'))
                    : round((float) $withdrawal->total_amount);

                DB::table('withdrawal_requests')->where('id', $withdrawal->id)->update([
                    'total_amount' => $total,
                ]);

                if ($withdrawal->ledger_transaction_id !== null) {
                    DB::table('transactions')->where('id', $withdrawal->ledger_transaction_id)->update([
                        'amount' => -$total,
                    ]);
                }
            }

            DB::table('users')->update([
                'balance' => DB::raw('ROUND(balance, 0)'),
            ]);

            $userIds = DB::table('transactions')->distinct()->orderBy('user_id')->pluck('user_id');

            foreach ($userIds as $userId) {
                $balance = 0.0;
                $transactions = DB::table('transactions')
                    ->where('user_id', $userId)
                    ->orderBy('id')
                    ->get(['id', 'amount']);

                foreach ($transactions as $transaction) {
                    $balance = round($balance + (float) $transaction->amount);

                    DB::table('transactions')->where('id', $transaction->id)->update([
                        'balance_after' => $balance,
                    ]);
                }

                DB::table('users')->where('id', $userId)->update([
                    'balance' => $balance,
                    'updated_at' => now(),
                ]);
            }
        });

        DB::statement('ALTER TABLE orders MODIFY charge_amount DECIMAL(10, 0) NULL');
        DB::statement('ALTER TABLE transactions MODIFY amount DECIMAL(10, 0) NOT NULL');
        DB::statement('ALTER TABLE transactions MODIFY balance_after DECIMAL(12, 0) NOT NULL');
        DB::statement('ALTER TABLE withdrawal_request_items MODIFY amount DECIMAL(10, 0) NOT NULL');
        DB::statement('ALTER TABLE withdrawal_requests MODIFY total_amount DECIMAL(12, 0) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY balance DECIMAL(12, 0) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders MODIFY charge_amount DECIMAL(10, 2) NULL');
        DB::statement('ALTER TABLE transactions MODIFY amount DECIMAL(10, 2) NOT NULL');
        DB::statement('ALTER TABLE transactions MODIFY balance_after DECIMAL(12, 2) NOT NULL');
        DB::statement('ALTER TABLE withdrawal_request_items MODIFY amount DECIMAL(10, 2) NOT NULL');
        DB::statement('ALTER TABLE withdrawal_requests MODIFY total_amount DECIMAL(12, 2) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY balance DECIMAL(12, 2) NOT NULL DEFAULT 0');
    }
};
