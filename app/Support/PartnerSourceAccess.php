<?php

namespace App\Support;

use App\Models\PartnerSourceAccessLog;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Services\LevelionApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Привязка партнёров к источникам (many-to-many) + аудит. */
final class PartnerSourceAccess
{
    /** @var array<int, \Illuminate\Support\Collection<int, int>> */
    private static array $accessibleIdsMemo = [];

    public static function usesCrmSources(): bool
    {
        return app(LevelionApiService::class)->isConfigured();
    }

    /** @return Collection<int, int> */
    public static function accessibleSourceIdsFor(User $user): Collection
    {
        $memoKey = (int) $user->id;
        if (isset(self::$accessibleIdsMemo[$memoKey])) {
            return self::$accessibleIdsMemo[$memoKey];
        }

        if ($user->hasElevatedAccess()) {
            return self::$accessibleIdsMemo[$memoKey] = collect();
        }

        if (self::usesCrmSources()) {
            $ids = collect();

            if ($user->isManager() || $user->isPartner()) {
                $ids = $user->allowedReferenceSources()
                    ->pluck('reference_sources.id')
                    ->map(fn ($id) => (int) $id);

                // Pivot тоже только по каталогу SuperPart — иначе «Листовка» в ACL снова покажет чужие заявки.
                $ids = ReferenceSource::query()
                    ->whereIn('id', $ids)
                    ->where('available_for_superpart', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                $shared = ReferenceSource::query()
                    ->where('shared_with_all_partners', true)
                    ->where('available_for_superpart', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                return self::$accessibleIdsMemo[$memoKey] = $ids->merge($shared)->unique()->values();
            }

            return self::$accessibleIdsMemo[$memoKey] = collect();
        }

        if ($user->isManager()) {
            return self::$accessibleIdsMemo[$memoKey] = $user->allowedSources()->pluck('sources.id')->map(fn ($id) => (int) $id)->values();
        }

        if ($user->isPartner()) {
            return self::$accessibleIdsMemo[$memoKey] = $user->allowedSources()->pluck('sources.id')->map(fn ($id) => (int) $id)->values();
        }

        return self::$accessibleIdsMemo[$memoKey] = collect();
    }

    /**
     * Начисления/выводы партнёра — только по заявкам с текущей РК из каталога SuperPart.
     * Если диспетчер сменил РК на «Листовку», charge в списке и к выводу пропадает вместе с заявкой.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Transaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Transaction>
     */
    public static function restrictTransactionsToAccessibleOrders(Builder $query, User $user): Builder
    {
        if (Schema::hasColumn('orders', 'excluded_at')) {
            $query->whereHas('order', function ($orderQuery) {
                $orderQuery->whereNull('excluded_at');
            });
        }

        if ($user->hasElevatedAccess()) {
            if (self::usesCrmSources()) {
                return $query->whereHas('order', function ($orderQuery) {
                    $orderQuery->whereHas('referenceSource', function ($refQuery) {
                        $refQuery->where('available_for_superpart', true);
                    });
                });
            }

            return $query;
        }

        $ids = self::accessibleSourceIdsFor($user);
        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        if (self::usesCrmSources()) {
            return $query->whereHas('order', function ($orderQuery) use ($ids) {
                $orderQuery->whereIn('reference_source_id', $ids);
            });
        }

        return $query->whereHas('order', function ($orderQuery) use ($ids) {
            $orderQuery->whereIn('source_id', $ids);
        });
    }

    /** Сбросить memo после изменения ACL в том же запросе. */
    public static function clearAccessibleIdsMemo(?int $userId = null): void
    {
        if ($userId === null) {
            self::$accessibleIdsMemo = [];

            return;
        }

        unset(self::$accessibleIdsMemo[$userId]);
    }

    /** Добавить один источник партнёру без снятия остальных (создание локального источника). */
    public static function attachOneToPartner(User $partner, ?int $referenceSourceId, ?int $localSourceId, ?User $actor = null): void
    {
        if (! $partner->isPartner()) {
            return;
        }

        if (self::usesCrmSources() && $referenceSourceId) {
            $already = $partner->allowedReferenceSources()->where('reference_sources.id', $referenceSourceId)->exists();
            if ($already) {
                return;
            }

            $partner->allowedReferenceSources()->attach($referenceSourceId);
            PartnerSourceAccessLog::query()->create([
                'actor_user_id' => $actor?->id,
                'partner_user_id' => $partner->id,
                'reference_source_id' => $referenceSourceId,
                'source_id' => $localSourceId,
                'action' => 'attach',
                'source_name' => ReferenceSource::query()->whereKey($referenceSourceId)->value('name'),
            ]);

            return;
        }

        if ($localSourceId) {
            $already = $partner->allowedSources()->where('sources.id', $localSourceId)->exists();
            if ($already) {
                return;
            }

            $partner->allowedSources()->attach($localSourceId);
            PartnerSourceAccessLog::query()->create([
                'actor_user_id' => $actor?->id,
                'partner_user_id' => $partner->id,
                'reference_source_id' => null,
                'source_id' => $localSourceId,
                'action' => 'attach',
                'source_name' => Source::query()->whereKey($localSourceId)->value('name'),
            ]);
        }
    }

    /**
     * Синхронизация источников партнёра с аудитом attach/detach.
     *
     * @param  list<int>  $sourceIds
     */
    public static function syncForPartner(User $partner, array $sourceIds, ?User $actor = null): void
    {
        if (! $partner->isPartner()) {
            return;
        }

        $sourceIds = collect($sourceIds)->map(fn ($id) => (int) $id)->unique()->filter()->values()->all();
        $actorId = $actor?->id;

        if (self::usesCrmSources()) {
            $before = $partner->allowedReferenceSources()->pluck('reference_sources.id')->map(fn ($id) => (int) $id)->all();
            $partner->allowedReferenceSources()->sync($sourceIds);
            $partner->allowedSources()->detach();
            self::clearAccessibleIdsMemo();

            $attached = array_values(array_diff($sourceIds, $before));
            $detached = array_values(array_diff($before, $sourceIds));

            $names = ReferenceSource::query()->whereIn('id', array_merge($attached, $detached))->pluck('name', 'id');

            foreach ($attached as $id) {
                PartnerSourceAccessLog::query()->create([
                    'actor_user_id' => $actorId,
                    'partner_user_id' => $partner->id,
                    'reference_source_id' => $id,
                    'source_id' => null,
                    'action' => 'attach',
                    'source_name' => $names[$id] ?? null,
                ]);
            }

            foreach ($detached as $id) {
                PartnerSourceAccessLog::query()->create([
                    'actor_user_id' => $actorId,
                    'partner_user_id' => $partner->id,
                    'reference_source_id' => $id,
                    'source_id' => null,
                    'action' => 'detach',
                    'source_name' => $names[$id] ?? null,
                ]);
            }

            return;
        }

        $before = $partner->allowedSources()->pluck('sources.id')->map(fn ($id) => (int) $id)->all();
        $partner->allowedSources()->sync($sourceIds);
        $partner->allowedReferenceSources()->detach();
        self::clearAccessibleIdsMemo();

        $attached = array_values(array_diff($sourceIds, $before));
        $detached = array_values(array_diff($before, $sourceIds));
        $names = Source::query()->whereIn('id', array_merge($attached, $detached))->pluck('name', 'id');

        foreach ($attached as $id) {
            PartnerSourceAccessLog::query()->create([
                'actor_user_id' => $actorId,
                'partner_user_id' => $partner->id,
                'reference_source_id' => null,
                'source_id' => $id,
                'action' => 'attach',
                'source_name' => $names[$id] ?? null,
            ]);
        }

        foreach ($detached as $id) {
            PartnerSourceAccessLog::query()->create([
                'actor_user_id' => $actorId,
                'partner_user_id' => $partner->id,
                'reference_source_id' => null,
                'source_id' => $id,
                'action' => 'detach',
                'source_name' => $names[$id] ?? null,
            ]);
        }
    }

    /**
     * Назначить набор партнёров на CRM-источник (many-to-many) с аудитом.
     *
     * @param  list<int>  $partnerIds
     */
    public static function syncPartnersForReferenceSource(int $referenceSourceId, array $partnerIds, ?User $actor = null): void
    {
        $partnerIds = collect($partnerIds)->map(fn ($id) => (int) $id)->unique()->filter()->values();
        $validPartnerIds = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->whereIn('id', $partnerIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $before = DB::table('user_allowed_reference_sources')
            ->join('users', 'users.id', '=', 'user_allowed_reference_sources.user_id')
            ->where('user_allowed_reference_sources.reference_source_id', $referenceSourceId)
            ->where('users.role', User::ROLE_PARTNER)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attach = array_values(array_diff($validPartnerIds, $before));
        $detach = array_values(array_diff($before, $validPartnerIds));
        $sourceName = ReferenceSource::query()->whereKey($referenceSourceId)->value('name');
        $actorId = $actor?->id;

        foreach ($attach as $partnerId) {
            $exists = DB::table('user_allowed_reference_sources')
                ->where('user_id', $partnerId)
                ->where('reference_source_id', $referenceSourceId)
                ->exists();

            if (! $exists) {
                DB::table('user_allowed_reference_sources')->insert([
                    'user_id' => $partnerId,
                    'reference_source_id' => $referenceSourceId,
                ]);
            }

            PartnerSourceAccessLog::query()->create([
                'actor_user_id' => $actorId,
                'partner_user_id' => $partnerId,
                'reference_source_id' => $referenceSourceId,
                'source_id' => null,
                'action' => 'attach',
                'source_name' => $sourceName,
            ]);
        }

        foreach ($detach as $partnerId) {
            DB::table('user_allowed_reference_sources')
                ->where('user_id', $partnerId)
                ->where('reference_source_id', $referenceSourceId)
                ->delete();

            PartnerSourceAccessLog::query()->create([
                'actor_user_id' => $actorId,
                'partner_user_id' => $partnerId,
                'reference_source_id' => $referenceSourceId,
                'source_id' => null,
                'action' => 'detach',
                'source_name' => $sourceName,
            ]);
        }

        self::clearAccessibleIdsMemo();
    }

    /**
     * Другие партнёры, у которых уже есть этот источник (для предупреждения в UI).
     *
     * @return Collection<int, User>
     */
    public static function otherPartnersUsingReferenceSource(int $referenceSourceId, ?int $exceptPartnerId = null): Collection
    {
        $q = DB::table('user_allowed_reference_sources')
            ->join('users', 'users.id', '=', 'user_allowed_reference_sources.user_id')
            ->where('user_allowed_reference_sources.reference_source_id', $referenceSourceId)
            ->where('users.role', User::ROLE_PARTNER);

        if ($exceptPartnerId) {
            $q->where('users.id', '!=', $exceptPartnerId);
        }

        $ids = $q->pluck('users.id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * @return Collection<int, User>
     */
    public static function partnersForReferenceSource(int $referenceSourceId): Collection
    {
        $ids = DB::table('user_allowed_reference_sources')
            ->join('users', 'users.id', '=', 'user_allowed_reference_sources.user_id')
            ->where('user_allowed_reference_sources.reference_source_id', $referenceSourceId)
            ->where('users.role', User::ROLE_PARTNER)
            ->pluck('users.id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * @return Collection<int, User>
     */
    public static function partnersForLocalSource(int $sourceId): Collection
    {
        $ids = DB::table('user_allowed_sources')
            ->join('users', 'users.id', '=', 'user_allowed_sources.user_id')
            ->where('user_allowed_sources.source_id', $sourceId)
            ->where('users.role', User::ROLE_PARTNER)
            ->pluck('users.id');

        $ownerId = Source::query()->whereKey($sourceId)->value('user_id');
        if ($ownerId) {
            $ids = $ids->push($ownerId)->unique();
        }

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);
    }
}
