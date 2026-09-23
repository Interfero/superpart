<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ТЗ §8 / §14: исключение заявки, журналы сверки и dead-letter.
 * Поля nullable, индексы добавляются без backfill-блокировки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'is_superpart_eligible')) {
                $table->boolean('is_superpart_eligible')->default(true)->after('crm_checksum');
            }
            if (! Schema::hasColumn('orders', 'excluded_at')) {
                $table->timestamp('excluded_at')->nullable()->after('is_superpart_eligible');
                $table->index('excluded_at');
            }
            if (! Schema::hasColumn('orders', 'exclusion_reason')) {
                $table->string('exclusion_reason', 64)->nullable()->after('excluded_at');
            }
            if (! Schema::hasColumn('orders', 'expected_reward')) {
                $table->unsignedInteger('expected_reward')->nullable()->after('charge_amount');
            }
            if (! Schema::hasColumn('orders', 'crm_source_id')) {
                $table->unsignedBigInteger('crm_source_id')->nullable()->after('reference_source_id');
                $table->index('crm_source_id');
            }
        });

        if (! Schema::hasTable('order_source_history')) {
            Schema::create('order_source_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('from_source_id')->nullable();
                $table->unsignedBigInteger('to_source_id')->nullable();
                $table->boolean('from_eligible')->nullable();
                $table->boolean('to_eligible')->nullable();
                $table->string('reason', 64)->nullable();
                $table->unsignedBigInteger('event_version')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('financial_discrepancies')) {
            Schema::create('financial_discrepancies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('kind', 64);
                $table->integer('ledger_amount')->default(0);
                $table->integer('expected_amount')->default(0);
                $table->integer('delta')->default(0);
                $table->unsignedBigInteger('event_version')->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('resolved_at')->nullable();

                $table->unique(['order_id', 'kind', 'event_version'], 'fin_disc_order_kind_ver_unique');
            });
        }

        if (! Schema::hasTable('sync_dead_letter')) {
            Schema::create('sync_dead_letter', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('error_code', 64)->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['created_at']);
            });
        }

        if (! Schema::hasTable('reconcile_runs')) {
            Schema::create('reconcile_runs', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 32); // cursor|full
                $table->boolean('dry_run')->default(false);
                $table->unsignedInteger('checked')->default(0);
                $table->unsignedInteger('equal_count')->default(0);
                $table->unsignedInteger('repaired')->default(0);
                $table->unsignedInteger('excluded')->default(0);
                $table->unsignedInteger('financial_discrepancy')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->string('status', 32)->default('ok');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reconcile_runs');
        Schema::dropIfExists('sync_dead_letter');
        Schema::dropIfExists('financial_discrepancies');
        Schema::dropIfExists('order_source_history');

        Schema::table('orders', function (Blueprint $table) {
            $cols = [];
            foreach (['is_superpart_eligible', 'excluded_at', 'exclusion_reason', 'expected_reward', 'crm_source_id'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
