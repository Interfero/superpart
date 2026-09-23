<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('levelion_source_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('crm_city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->unsignedBigInteger('superpart_partner_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sources');
    }
};
