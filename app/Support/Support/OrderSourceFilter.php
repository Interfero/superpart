<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ReferenceSource;
use App\Models\Source;
use Illuminate\Database\Eloquent\Builder;

class OrderSourceFilter
{
    /**
     * Фильтр по источнику: ключи «r{id}» — CRM-справочник, «s{id}» — локальный источник (legacy).
     */
    public static function apply(Builder $query, ?string $filterSource): void
    {
        if ($filterSource === null || $filterSource === '') {
            return;
        }

        if (str_starts_with((string) $filterSource, 'r')) {
            $query->where('reference_source_id', (int) substr((string) $filterSource, 1));

            return;
        }

        if (str_starts_with((string) $filterSource, 's')) {
            $query->where('source_id', (int) substr((string) $filterSource, 1))
                ->whereNull('reference_source_id');

            return;
        }

        $query->where('source_id', $filterSource)->whereNull('reference_source_id');
    }

    /**
     * Опции для выпадающих фильтров по заявкам пользователя.
     *
     * @return array<string, string> key => label
     */
    public static function optionsForOrdersQuery(Builder $ordersBase): array
    {
        $orders = (clone $ordersBase);

        $refIds = (clone $orders)->whereNotNull('reference_source_id')->distinct()->pluck('reference_source_id');
        $fromRef = ReferenceSource::whereIn('id', $refIds)->pluck('name', 'id');

        $locIds = (clone $orders)->whereNull('reference_source_id')->whereNotNull('source_id')->distinct()->pluck('source_id');
        $fromLoc = Source::whereIn('id', $locIds)->pluck('name', 'id');

        $out = [];
        foreach ($fromRef as $id => $name) {
            $out['r'.$id] = $name;
        }
        foreach ($fromLoc as $id => $name) {
            $out['s'.$id] = $name;
        }

        return $out;
    }

    public static function displayName(Order $order): string
    {
        if ($order->reference_source_id !== null) {
            $order->loadMissing('referenceSource');

            return $order->referenceSource?->name ?? '—';
        }

        $order->loadMissing('source');

        return $order->source?->name ?? '—';
    }
}
