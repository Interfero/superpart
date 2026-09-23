<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ReferenceSource;
use App\Services\OrderSnapshotApplier;
use Illuminate\Support\Facades\DB;

/** ТЗ FR-SRC: не-SP источники не в рабочих разделах SuperPart. */
final class LeaveSuperpartCatalog
{
    public static function hideReference(ReferenceSource $ref): int
    {
        $ref->available_for_superpart = false;
        $ref->shared_with_all_partners = false;
        $ref->save();

        DB::table('user_allowed_reference_sources')
            ->where('reference_source_id', $ref->id)
            ->delete();

        return self::excludeOrdersOfReference((int) $ref->id);
    }

    public static function excludeOrdersOfReference(int $referenceSourceId): int
    {
        $applier = app(OrderSnapshotApplier::class);
        $count = 0;

        Order::query()
            ->where('reference_source_id', $referenceSourceId)
            ->where(function ($q) {
                $q->whereNull('excluded_at')
                    ->orWhere('charge_amount', '>', 0);
            })
            ->orderBy('id')
            ->each(function (Order $order) use ($applier, &$count) {
                $applier->applyLeaveSuperpart($order);
                $count++;
            });

        return $count;
    }

    public static function hideNonSpAndLeaflets(): array
    {
        $leafletRefs = ReferenceSource::query()
            ->where('name', 'like', '%листов%')
            ->get();
        foreach ($leafletRefs as $ref) {
            self::hideReference($ref);
        }

        $hiddenOrders = 0;
        $refs = ReferenceSource::query()
            ->where('available_for_superpart', false)
            ->pluck('id');
        foreach ($refs as $id) {
            $hiddenOrders += self::excludeOrdersOfReference((int) $id);
        }

        return [
            'leaflet_refs' => $leafletRefs->count(),
            'non_sp_refs' => $refs->count(),
            'orders_left' => $hiddenOrders,
        ];
    }
}
