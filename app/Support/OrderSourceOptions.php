<?php

namespace App\Support;

use App\Models\ReferenceSource;
use App\Models\User;
use App\Services\LevelionApiService;
use Illuminate\Support\Collection;

/** Источники для формы «Регистрация заявки» и ACL по назначенным источникам. */
final class OrderSourceOptions
{
    /**
     * @return Collection<int, ReferenceSource>
     */
    public static function referenceSourcesForUser(User $user): Collection
    {
        if (! app(LevelionApiService::class)->isConfigured()) {
            return collect();
        }

        $ownerId = $user->effectiveOwnerId();
        LocalSourceReferenceMirror::syncAllForOwner($ownerId);

        return ReferenceSource::queryForOrderForm($user)
            ->get(['id', 'name', 'city_name', 'local_source_id', 'superpart_partner_id', 'levelion_source_id'])
            ->unique('id')
            ->sortBy(fn (ReferenceSource $source) => mb_strtolower($source->name))
            ->values();
    }

    public static function referenceSourceVisibleToUser(ReferenceSource $referenceSource, User $user): bool
    {
        if (! $referenceSource->isSelectableForOrderForm()) {
            return false;
        }

        if ($user->hasElevatedAccess()) {
            return true;
        }

        $allowedIds = PartnerSourceAccess::accessibleSourceIdsFor($user);

        return $allowedIds->contains((int) $referenceSource->id);
    }

    /**
     * Источники, назначенные партнёру (для выбора менеджеру).
     *
     * @return Collection<int, ReferenceSource>
     */
    public static function referenceSourcesForPartnerOwner(int $ownerUserId): Collection
    {
        LocalSourceReferenceMirror::syncAllForOwner($ownerUserId);

        $partner = User::query()->find($ownerUserId);
        if (! $partner) {
            return collect();
        }

        $ids = PartnerSourceAccess::accessibleSourceIdsFor($partner);
        if ($ids->isEmpty()) {
            return collect();
        }

        return ReferenceSource::query()
            ->whereIn('id', $ids->all())
            ->availableForSuperpart()
            ->orderBy('name')
            ->get(['id', 'name', 'city_name', 'superpart_partner_id', 'levelion_source_id']);
    }
}
