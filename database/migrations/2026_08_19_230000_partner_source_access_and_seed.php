<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_source_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('partner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('reference_source_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('action', 16); // attach|detach
            $table->string('source_name')->nullable();
            $table->timestamps();

            $table->index(['partner_user_id', 'created_at']);
        });

        // Заполнить pivot партнёров из текущих закреплений (CRM + локальные зеркала + заказы).
        $partners = DB::table('users')->where('role', 'partner')->pluck('id');

        foreach ($partners as $partnerId) {
            $refIds = collect();

            $refIds = $refIds->merge(
                DB::table('reference_sources')
                    ->where('superpart_partner_id', $partnerId)
                    ->where('available_for_superpart', true)
                    ->pluck('id')
            );

            $refIds = $refIds->merge(
                DB::table('orders')
                    ->where('user_id', $partnerId)
                    ->whereNotNull('reference_source_id')
                    ->distinct()
                    ->pluck('reference_source_id')
            );

            $refIds = $refIds->unique()->filter()->values();

            foreach ($refIds as $refId) {
                $exists = DB::table('user_allowed_reference_sources')
                    ->where('user_id', $partnerId)
                    ->where('reference_source_id', $refId)
                    ->exists();

                if (! $exists) {
                    DB::table('user_allowed_reference_sources')->insert([
                        'user_id' => $partnerId,
                        'reference_source_id' => $refId,
                    ]);
                }
            }

            $localIds = DB::table('sources')
                ->where('user_id', $partnerId)
                ->whereNull('deleted_at')
                ->pluck('id');

            $localIds = $localIds->merge(
                DB::table('orders')
                    ->where('user_id', $partnerId)
                    ->whereNotNull('source_id')
                    ->distinct()
                    ->pluck('source_id')
            )->unique()->filter()->values();

            foreach ($localIds as $sourceId) {
                $exists = DB::table('user_allowed_sources')
                    ->where('user_id', $partnerId)
                    ->where('source_id', $sourceId)
                    ->exists();

                if (! $exists) {
                    DB::table('user_allowed_sources')->insert([
                        'user_id' => $partnerId,
                        'source_id' => $sourceId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_source_access_logs');
    }
};
