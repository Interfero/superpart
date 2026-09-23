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
     * Опции для фильтров на главной / в отчётах / начислениях — только название (без #ID).
     *
     * @return array<string, string> key => label
     */
    public static function optionsForOrdersQuery(Builder $ordersBase): array
    {
        $orders = (clone $ordersBase);

        $refIds = (clone $orders)->whereNotNull('reference_source_id')->distinct()->pluck('reference_source_id');
        $fromRef = ReferenceSource::whereIn('id', $refIds)->get(['id', 'name', 'levelion_source_id']);

        $locIds = (clone $orders)->whereNull('reference_source_id')->whereNotNull('source_id')->distinct()->pluck('source_id');
        $fromLoc = Source::whereIn('id', $locIds)->pluck('name', 'id');

        $out = [];
        foreach ($fromRef as $ref) {
            $out['r'.$ref->id] = self::nameOnly($ref->name);
        }
        foreach ($fromLoc as $id => $name) {
            $out['s'.$id] = self::nameOnly((string) $name);
        }

        return $out;
    }

    /** Подпись источника в списках заказов / отчётах — только имя. */
    public static function displayName(Order $order): string
    {
        if ($order->reference_source_id !== null) {
            $order->loadMissing('referenceSource');
            $ref = $order->referenceSource;
            if (! $ref) {
                return '—';
            }

            return self::nameOnly($ref->name);
        }

        $order->loadMissing('source');
        if (! $order->source) {
            return '—';
        }

        return self::nameOnly($order->source->name);
    }

    /** Подпись для раздела «Источники» — ID CRM + название. */
    public static function labelWithCrmId(ReferenceSource $ref): string
    {
        return '#'.$ref->crmId().' — '.self::nameOnly($ref->name);
    }

    public static function nameOnly(?string $name): string
    {
        $name = trim((string) $name);
        // Если в БД/CRM попало «#45 — Мастер Николай» — в списках оставляем только имя.
        $name = preg_replace('/^#\d+\s*[—–-]\s*/u', '', $name) ?? $name;
        $name = trim($name);

        return $name !== '' ? $name : '—';
    }
}
