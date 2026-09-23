<?php

use App\Support\WorkTypeCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WorkTypeCatalog::sync();
    }

    public function down(): void
    {
        //
    }
};
