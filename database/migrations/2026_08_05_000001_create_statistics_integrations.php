<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistics_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24);
            $table->string('name');
            $table->string('source_name');
            $table->text('base_url')->nullable();
            $table->string('external_account_id')->nullable();
            $table->longText('credentials')->nullable();
            $table->longText('settings')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->index(['provider', 'enabled']);
            $table->index('source_name');
        });

        Schema::create('statistics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statistics_integration_id')
                ->constrained('statistics_integrations')
                ->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->json('metrics');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(
                ['statistics_integration_id', 'date_from', 'date_to'],
                'statistics_snapshot_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistics_snapshots');
        Schema::dropIfExists('statistics_integrations');
    }
};
