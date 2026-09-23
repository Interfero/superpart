<?php

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Support\LocalSourceReferenceMirror;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('reference_sources', 'local_source_id')) {
                $table->foreignId('local_source_id')
                    ->nullable()
                    ->unique()
                    ->after('levelion_source_id')
                    ->constrained('sources')
                    ->nullOnDelete();
            }
        });

        DB::statement('ALTER TABLE reference_sources MODIFY levelion_source_id BIGINT UNSIGNED NULL');

        Source::query()->orderBy('id')->each(function (Source $source) {
            LocalSourceReferenceMirror::sync($source);
        });
    }

    public function down(): void
    {
        ReferenceSource::query()->whereNotNull('local_source_id')->delete();

        Schema::table('reference_sources', function (Blueprint $table) {
            if (Schema::hasColumn('reference_sources', 'local_source_id')) {
                $table->dropUnique(['local_source_id']);
                $table->dropConstrainedForeignId('local_source_id');
            }
        });

        DB::statement('ALTER TABLE reference_sources MODIFY levelion_source_id BIGINT UNSIGNED NOT NULL');
    }
};
