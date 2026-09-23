<?php


use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;


return new class extends Migration

{

    public function up(): void

    {

        Schema::create('portal_notifications', function (Blueprint $table) {

            $table->id();


            $table->foreignId('user_id')

                ->constrained()

                ->cascadeOnDelete();


            $table->string('type', 50)->default('system');

            $table->string('title', 255);

            $table->text('message')->nullable();

            $table->string('url')->nullable();


            $table->timestamp('read_at')->nullable();

            $table->timestamps();


            $table->index(['user_id', 'read_at']);

            $table->index(['user_id', 'created_at']);

        });

    }


    public function down(): void

    {

        Schema::dropIfExists('portal_notifications');

    }

};
