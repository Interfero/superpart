<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ТЗ FR-FIN-01: метаданные correction / идемпотентность.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'related_transaction_id')) {
                $table->unsignedBigInteger('related_transaction_id')->nullable()->after('order_id');
            }
            if (! Schema::hasColumn('transactions', 'reason_code')) {
                $table->string('reason_code', 64)->nullable()->after('operation_type');
            }
            if (! Schema::hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable()->after('reason_code');
            }
            if (! Schema::hasColumn('transactions', 'reward_rule')) {
                $table->string('reward_rule', 64)->nullable()->after('idempotency_key');
            }
        });

        $this->ensureUniqueIdempotencyKey();
        $this->ensureRelatedFk();
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            try {
                $table->dropForeign(['related_transaction_id']);
            } catch (\Throwable) {
                // ignore
            }
            try {
                $table->dropUnique(['idempotency_key']);
            } catch (\Throwable) {
                // ignore
            }

            $cols = [];
            foreach (['related_transaction_id', 'reason_code', 'idempotency_key', 'reward_rule'] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }

    private function ensureUniqueIdempotencyKey(): void
    {
        if (! Schema::hasColumn('transactions', 'idempotency_key')) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?',
            ['transactions', 'transactions_idempotency_key_unique']
        );
        if ((int) ($exists->c ?? 0) > 0) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('idempotency_key');
        });
    }

    private function ensureRelatedFk(): void
    {
        if (! Schema::hasColumn('transactions', 'related_transaction_id')) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.table_constraints
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND constraint_type = ?
               AND constraint_name = ?',
            ['transactions', 'FOREIGN KEY', 'transactions_related_transaction_id_foreign']
        );
        if ((int) ($exists->c ?? 0) > 0) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('related_transaction_id')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });
    }
};
