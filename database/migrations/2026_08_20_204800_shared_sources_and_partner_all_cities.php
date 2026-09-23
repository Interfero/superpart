<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('reference_sources', 'shared_with_all_partners')) {
                $table->boolean('shared_with_all_partners')->default(false)->after('available_for_superpart');
            }
        });

        // Партнёрам без городов — все доступные города портала.
        $cityIds = DB::table('cities')
            ->where('is_available', true)
            ->when(
                DB::table('cities')->whereNotNull('levelion_city_id')->exists(),
                fn ($q) => $q->whereNotNull('levelion_city_id')
            )
            ->pluck('id');

        // Дедуп по имени: оставляем с levelion_city_id, иначе меньший id.
        $cities = DB::table('cities')
            ->whereIn('id', $cityIds)
            ->orderByRaw('CASE WHEN levelion_city_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->get(['id', 'name', 'levelion_city_id']);

        $picked = [];
        foreach ($cities as $city) {
            $key = mb_strtolower(trim((string) $city->name));
            if (! isset($picked[$key])) {
                $picked[$key] = (int) $city->id;
            }
        }
        $uniqueCityIds = array_values($picked);

        $partnerIds = DB::table('users')->where('role', 'partner')->pluck('id');

        foreach ($partnerIds as $partnerId) {
            foreach ($uniqueCityIds as $cityId) {
                $exists = DB::table('user_allowed_cities')
                    ->where('user_id', $partnerId)
                    ->where('city_id', $cityId)
                    ->exists();

                if (! $exists) {
                    DB::table('user_allowed_cities')->insert([
                        'user_id' => $partnerId,
                        'city_id' => $cityId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('reference_sources', function (Blueprint $table) {
            if (Schema::hasColumn('reference_sources', 'shared_with_all_partners')) {
                $table->dropColumn('shared_with_all_partners');
            }
        });
    }
};
