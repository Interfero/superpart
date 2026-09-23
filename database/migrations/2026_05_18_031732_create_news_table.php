<?php


use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;


return new class extends Migration

{

    public function up(): void

    {

        Schema::create('news', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 255);

            $table->text('body');

            $table->timestamps();

        });

    }


    public function down(): void

    {

        Schema::dropIfExists('news');

    }

};
