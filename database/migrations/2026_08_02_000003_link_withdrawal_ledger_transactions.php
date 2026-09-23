<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'withdrawal_requests_ledger_transaction_unique';

    private const FOREIGN_KEY = 'withdrawal_requests_ledger_transaction_fk';

    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('ledger_transaction_id')->nullable()->after('total_amount');
            $table->unique('ledger_transaction_id', self::UNIQUE_INDEX);
            $table->foreign('ledger_transaction_id', self::FOREIGN_KEY)
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });

        DB::transaction(function () {
            $withdrawalIds = DB::table('withdrawal_requests')
                ->where('status', 'completed')
                ->whereNull('ledger_transaction_id')
                ->orderBy('updated_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($withdrawalIds as $withdrawalId) {
                $withdrawal = DB::table('withdrawal_requests')
                    ->where('id', $withdrawalId)
                    ->lockForUpdate()
                    ->first();

                if (! $withdrawal || $withdrawal->ledger_transaction_id !== null) {
                    continue;
                }

                DB::table('users')->where('id', $withdrawal->user_id)->lockForUpdate()->first();

                $previous = DB::table('transactions')
                    ->where('user_id', $withdrawal->user_id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('balance_after');

                $amount = round((float) $withdrawal->total_amount, 2);

                if ($amount <= 0) {
                    throw new RuntimeException('Невозможно провести выплату с неположительной суммой.');
                }

                $newBalance = round((float) ($previous ?? 0) - $amount, 2);
                $completedAt = $withdrawal->updated_at ?? now();

                $transactionId = DB::table('transactions')->insertGetId([
                    'user_id' => $withdrawal->user_id,
                    'order_id' => null,
                    'amount' => -$amount,
                    'operation_type' => 'withdrawal',
                    'balance_after' => $newBalance,
                    'completed_at' => $completedAt,
                    'created_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);

                DB::table('withdrawal_requests')->where('id', $withdrawalId)->update([
                    'ledger_transaction_id' => $transactionId,
                ]);

                DB::table('users')->where('id', $withdrawal->user_id)->update([
                    'balance' => $newBalance,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        $linked = DB::table('withdrawal_requests')
            ->whereNotNull('ledger_transaction_id')
            ->get(['user_id', 'ledger_transaction_id']);

        $userIds = $linked->pluck('user_id')->unique()->values();
        $transactionIds = $linked->pluck('ledger_transaction_id')->unique()->values();

        DB::transaction(function () use ($userIds, $transactionIds) {
            DB::table('withdrawal_requests')->whereNotNull('ledger_transaction_id')->update([
                'ledger_transaction_id' => null,
            ]);

            DB::table('transactions')
                ->whereIn('id', $transactionIds)
                ->where('operation_type', 'withdrawal')
                ->delete();

            foreach ($userIds as $userId) {
                $balance = 0.0;
                $transactions = DB::table('transactions')
                    ->where('user_id', $userId)
                    ->orderBy('id')
                    ->get(['id', 'amount']);

                foreach ($transactions as $transaction) {
                    $balance = round($balance + (float) $transaction->amount, 2);
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

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign(self::FOREIGN_KEY);
            $table->dropUnique(self::UNIQUE_INDEX);
            $table->dropColumn('ledger_transaction_id');
        });
    }
};
