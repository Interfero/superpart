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

        return $query->get(['id', 'name', 'timezone', 'levelion_city_id'])
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
     * Города для фильтров списков заявок: у партнёра/менеджера — только назначенные; у разработчика/гендиректора — по факту заявок в выборке.
     *
     * @return array<int, string> id => название
     */
    public static function cityMapForOrderFilters(User $user, Builder $ordersScope): array
    {
        if ($user->hasRestrictedCityAccess()) {
            return City::query()
                ->whereIn('cities.id', $user->allowedCities()->pluck('cities.id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        $cityIds = (clone $ordersScope)->whereNotNull('city_id')->distinct()->pluck('city_id');

        return City::whereIn('id', $cityIds)->orderBy('name')->pluck('name', 'id')->toArray();
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