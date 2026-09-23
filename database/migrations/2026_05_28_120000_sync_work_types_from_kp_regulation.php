<?php

use App\Support\WorkTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            if (! Schema::hasColumn('work_types', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('id');
            }
            if (! Schema::hasColumn('work_types', 'equipment_code')) {
                $table->string('equipment_code', 64)->nullable()->after('sort_order');
            }
        });

        WorkTypeCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            if (Schema::hasColumn('work_types', 'equipment_code')) {
                $table->dropColumn('equipment_code');
            }
            if (Schema::hasColumn('work_types', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
