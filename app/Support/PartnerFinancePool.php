<?php

namespace App\Support;

use App\Models\ReferenceSource;
use App\Models\User;
use App\Services\LevelionApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Общий денежный контур партнёров при общих источниках (shared_with_all_partners).
 * Все партнёры видят одни и те же баланс / начисления / доступное к выводу.
 */
final class PartnerFinancePool
{
    private const CACHE_KEY = 'partner_finance_pool:v1';

    public static function isActive(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY.':active', 60, function () {
            if (! app(LevelionApiService::class)->isConfigured()) {
                return false;
            }

            return ReferenceSource::query()
                ->where('shared_with_all_partners', true)
                ->where('available_for_superpart', true)
                ->exists();
        });
    }

    public static function usesPool(User $user): bool
    {
        if ($user->hasElevatedAccess()) {
            return false;
        }

        if (! $user->isPartner() && ! $user->isManager()) {
            return false;
        }

        return self::isActive();
    }

    /** @return list<int> */
    public static function partnerIds(): array
    {
        return Cache::remember(self::CACHE_KEY.':ids', 60, function () {
            return User::query()
                ->where('role', User::ROLE_PARTNER)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        });
    }

    public static function balance(): float
    {
        $ids = self::partnerIds();
        if ($ids === []) {
            return 0.0;
        }

        return round((float) User::query()->whereIn('id', $ids)->sum('balance'));
    }

    /** @param  Builder<\App\Models\Transaction>  $query */
    public static function scopeTransactions(Builder $query): Builder
    {
        $ids = self::partnerIds();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('transactions.user_id', $ids);
    }

    /** @param  Builder<\App\Models\WithdrawalRequest>  $query */
    public static function scopeWithdrawals(Builder $query): Builder
    {
        $ids = self::partnerIds();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('withdrawal_requests.user_id', $ids);
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY.':active');
        Cache::forget(self::CACHE_KEY.':ids');
    }
}
