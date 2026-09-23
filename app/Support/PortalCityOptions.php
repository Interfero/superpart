<?php

namespace App\Support;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PortalCityOptions
{
    public static function applyOrderFormCityScope(Builder $query, User $user): void
    {
        if ($user->hasRestrictedCityAccess()) {
            $query->whereIn('id', $user->allowedCities()->pluck('cities.id'));
        }
    }

    /**
     * Города для формы заявки: без дублей по названию, при CRM — только из справочника.
     *
     * @return \Illuminate\Support\Collection<int, City>
     */
    public static function citiesForOrderForm(User $user, bool $crmConfigured): \Illuminate\Support\Collection
    {
        $query = City::query()->where('is_available', true);

        if ($crmConfigured) {
            $query->whereNotNull('levelion_city_id');
        }

        self::applyOrderFormCityScope($query, $user);

        return self::deduplicateCitiesByName(
            $query->get(['id', 'name', 'timezone', 'levelion_city_id'])
        );
    }

    /**
     * Города для формы сотрудников (создание/редактирование): без дублей по названию.
     *
     * @return \Illuminate\Support\Collection<int, City>
     */
    public static function citiesForManagementForm(User $user, bool $crmConfigured): \Illuminate\Support\Collection
    {
        if ($user->isPartner()) {
            $cities = $user->allowedCities()
                ->orderBy('cities.name')
                ->get(['cities.id', 'cities.name', 'cities.levelion_city_id']);

            return self::deduplicateCitiesByName($cities);
        }

        $query = City::query()->where('is_available', true);

        if ($crmConfigured) {
            $query->whereNotNull('levelion_city_id');
        }

        return self::deduplicateCitiesByName(
            $query->orderBy('name')->get(['id', 'name', 'levelion_city_id'])
        );
    }

    /**
     * Один город на название; при дублях в БД оставляем запись с levelion_city_id, затем с меньшим id.
     *
     * @param  \Illuminate\Support\Collection<int, City>  $cities
     * @return \Illuminate\Support\Collection<int, City>
     */
    public static function deduplicateCitiesByName(\Illuminate\Support\Collection $cities): \Illuminate\Support\Collection
    {
        return $cities
            ->sortBy([
                fn (City $city) => mb_strtolower(trim($city->name)),
                fn (City $city) => $city->levelion_city_id === null ? 1 : 0,
                'id',
            ])
            ->unique(fn (City $city) => mb_strtolower(trim($city->name)))
            ->sortBy(fn (City $city) => mb_strtolower(trim($city->name)), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Все доступные города портала (для автоназначения партнёру).
     *
     * @return list<int>
     */
    public static function allAvailableCityIds(bool $crmConfigured): array
    {
        $query = City::query()->where('is_available', true);

        if ($crmConfigured) {
            $query->whereNotNull('levelion_city_id');
        }

        return self::deduplicateCitiesByName(
            $query->orderBy('name')->get(['id', 'name', 'levelion_city_id'])
        )->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Города для фильтров списков заявок: у партнёра/менеджера — назначенные
     * плюс города, по которым уже есть заявки в его выборке (чтобы старые заказы не «пропадали»).
     * У elevated — города из фактических заявок.
     *
     * @return array<int, string> id => название
     */
    public static function cityMapForOrderFilters(User $user, Builder $ordersScope): array
    {
        $orderCityIds = (clone $ordersScope)->whereNotNull('city_id')->distinct()->pluck('city_id');

        if ($user->hasRestrictedCityAccess()) {
            $allowedIds = $user->allowedCities()->pluck('cities.id');
            $cityIds = $allowedIds->merge($orderCityIds)->unique()->filter()->values();
        } else {
            $cityIds = $orderCityIds;
        }

        if ($cityIds->isEmpty()) {
            return [];
        }

        return City::query()
            ->whereIn('id', $cityIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Названия городов для фильтра таблицы отзывов.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function cityNamesForReviewFilters(User $user, Builder $reviewsScope): \Illuminate\Support\Collection
    {
        if ($user->hasRestrictedCityAccess()) {
            return $user->allowedCities()->orderBy('name')->pluck('name');
        }

        $cityIds = (clone $reviewsScope)->distinct()->pluck('city_id')->filter()->unique();

        return City::whereIn('id', $cityIds)->orderBy('name')->pluck('name');
    }
}