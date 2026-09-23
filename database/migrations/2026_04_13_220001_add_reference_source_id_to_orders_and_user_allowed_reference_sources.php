<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('reference_source_id')
                ->nullable()
                ->after('source_id')
                ->constrained('reference_sources')
                ->nullOnDelete();
        });

        Schema::create('user_allowed_reference_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reference_source_id')->constrained('reference_sources')->cascadeOnDelete();
            $table->unique(['user_id', 'reference_source_id'], 'uarfs_user_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_allowed_reference_sources');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['reference_source_id']);
            $table->dropColumn('reference_source_id');
        });
    }
};
