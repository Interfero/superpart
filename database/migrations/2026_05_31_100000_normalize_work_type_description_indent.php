<?php

use App\Models\WorkType;
use App\Support\OrderEquipmentRegulation;
use App\Support\WorkTypeCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WorkTypeCatalog::sync();

        WorkType::query()->each(function (WorkType $workType) {
            $normalized = OrderEquipmentRegulation::normalizeDescriptionText(
                (string) $workType->description
            );

            if ($normalized !== $workType->description) {
                $workType->update(['description' => $normalized]);
            }
        });
    }

    public function down(): void
    {
        //
    }
};
