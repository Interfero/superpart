<?php

use App\Support\UserBalance;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        UserBalance::syncAll();
    }

    public function down(): void
    {
        //
    }
};
