<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'crm_sync_version')) {
                $table->unsignedBigInteger('crm_sync_version')->default(0)->after('sync_status');
                $table->index('crm_sync_version');
            }
            if (! Schema::hasColumn('orders', 'crm_checksum')) {
                $table->string('crm_checksum', 64)->nullable()->after('crm_sync_version');
            }
        });

        if (! Schema::hasTable('sync_inbox')) {
            Schema::create('sync_inbox', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('event_version')->default(0);
                $table->string('checksum', 64)->nullable();
                $table->string('event_type', 64)->nullable();
                $table->string('result', 32)->default('received'); // received|applied|duplicate|rejected|stale
                $table->string('error_code', 64)->nullable();
                $table->unsignedSmallInteger('attempts')->default(1);
                $table->timestamp('received_at')->useCurrent();
                $table->timestamp('applied_at')->nullable();
                $table->json('payload')->nullable();

                $table->index(['order_id', 'event_version']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_inbox');
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'crm_checksum')) {
                $table->dropColumn('crm_checksum');
            }
            if (Schema::hasColumn('orders', 'crm_sync_version')) {
                $table->dropIndex(['crm_sync_version']);
                $table->dropColumn('crm_sync_version');
            }
        });
    }
};
