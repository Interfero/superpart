<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('withdrawal_requests', 'batch_id')) {
            Schema::table('withdrawal_requests', function (Blueprint $table) {
                $table->uuid('batch_id')->nullable()->after('user_id')->index();
            });
        }

        $groups = DB::table('withdrawal_requests')
            ->select('created_at', 'requisites', DB::raw('COUNT(*) as c'), DB::raw('MIN(id) as min_id'))
            ->whereNull('batch_id')
            ->groupBy('created_at', 'requisites')
            ->having('c', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $batchId = (string) Str::uuid();
            DB::table('withdrawal_requests')
                ->where('created_at', $group->created_at)
                ->where('requisites', $group->requisites)
                ->whereNull('batch_id')
                ->update(['batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('withdrawal_requests', 'batch_id')) {
            Schema::table('withdrawal_requests', function (Blueprint $table) {
                $table->dropIndex(['batch_id']);
                $table->dropColumn('batch_id');
            });
        }
    }
};
