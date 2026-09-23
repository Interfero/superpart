<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {

            $table->text('comment')
                ->nullable()
                ->after('review_url');

            $table->text('result')
                ->nullable()
                ->after('comment');

            $table->boolean('refund')
                ->default(false)
                ->after('result');

            $table->json('photos')
                ->nullable()
                ->after('refund');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {

            $table->dropColumn([
                'comment',
                'result',
                'refund',
                'photos',
            ]);
        });
    }
};