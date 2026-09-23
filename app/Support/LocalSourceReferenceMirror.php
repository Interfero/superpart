<?php

namespace App\Support;

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Services\LevelionApiService;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * Локальные источники партнёра (таблица sources) → reference_sources и CRM.
 * Источник правды — SuperPart; при сохранении отправляется в CRM.
 */
final class LocalSourceReferenceMirror
{
    public static function isActive(): bool
    {
        return app(LevelionApiService::class)->isConfigured();
    }

    public static function sync(Source $source): ?ReferenceSource
    {
        if (! self::isActive()) {
            return null;
        }

        $ref = ReferenceSource::query()->firstOrNew(['local_source_id' => $source->id]);
        $ref->fill([
            'name' => $source->name,
            // Владелец для CRM-атрибуции; shared = видят/могут брать все партнёры.
            'superpart_partner_id' => (int) $source->user_id,
            'shared_with_all_partners' => true,
            'available_for_superpart' => true,
            'synced_at' => now(),
        ]);

        if (! $ref->exists) {
            $ref->levelion_source_id = null;
        }

        $ref->save();

        // При «общий» ACL на одного партнёра не нужен — видят все через shared_with_all_partners.
        // Владельца всё же привязываем для телефонов/карточки источника.
        self::attachToOwnerTeam($ref, (int) $source->user_id);
        self::pushToCrm($source, $ref);

        return $ref->fresh();
    }

    private static function pushToCrm(Source $source, ReferenceSource $ref): void
    {
        $api = app(LevelionApiService::class);

        if ($ref->levelion_source_id !== null && (int) $ref->levelion_source_id > 0) {
            $api->updateCrmSourceFromLocal($source, (int) $ref->levelion_source_id);

            return;
        }

        $push = $api->pushLocalSourceToCrm($source);

        if ($push['crm_source_id'] !== null) {
            $ref->levelion_source_id = $push['crm_source_id'];
            $ref->synced_at = now();
            $ref->save();
        }
    }

    private static function attachToOwnerTeam(ReferenceSource $ref, int $ownerUserId): void
    {
        $owner = User::query()->find($ownerUserId);

        if (! $owner) {
            return;
        }

        $owner->allowedReferenceSources()->syncWithoutDetaching([$ref->id]);

        if (! $owner->isPartner()) {
            return;
        }

        $owner->managedUsers()
            ->where('role', User::ROLE_MANAGER)
            ->each(function (User $manager) use ($ref) {
                $manager->allowedReferenceSources()->syncWithoutDetaching([$ref->id]);
            });
    }

    /** Удалить локальный источник и зеркало в CRM. */
    public static function deleteLocalSource(Source $source): void
    {
        self::forget($source);
        $source->delete();
    }

    /** Убрать CRM-источник из каталога SuperPart. Строку CRM не удаляем. */
    public static function deleteOrphanReference(ReferenceSource $referenceSource): void
    {
        if ($referenceSource->local_source_id !== null) {
            $local = Source::query()->find($referenceSource->local_source_id);

            if ($local) {
                self::deleteLocalSource($local);

                return;
            }
        }

        $crmWarning = null;
        if ($referenceSource->levelion_source_id) {
            $result = app(LevelionApiService::class)->revokeSuperpartCatalog(
                (int) $referenceSource->levelion_source_id
            );
            if (! ($result['ok'] ?? false)) {
                $crmWarning = $result['error'] ?? 'CRM не ответила';
                \Illuminate\Support\Facades\Log::warning('deleteOrphanReference: CRM unreachable, hiding locally', [
                    'reference_source_id' => $referenceSource->id,
                    'error' => $crmWarning,
                ]);
            }
        }

        LeaveSuperpartCatalog::hideReference($referenceSource);
    }

    public static function forget(Source $source): void
    {
        $ref = ReferenceSource::query()
            ->where('local_source_id', $source->id)
            ->first();

        if ($ref?->levelion_source_id) {
            $result = app(LevelionApiService::class)->deleteCrmSource(
                (int) $ref->levelion_source_id,
                (int) $source->id
            );

            if (! $result['ok']) {
                throw new \RuntimeException($result['error'] ?? 'Не удалось удалить источник в CRM.');
            }
        }

        if ($ref) {
            self::deleteReferenceSourceRow((int) $ref->id);
        }
    }

    private static function deleteReferenceSourceRow(int $referenceSourceId): void
    {
        self::runWithMysqlRetry(function () use ($referenceSourceId): void {
            DB::reconnect();

            DB::table('user_allowed_reference_sources')
                ->where('reference_source_id', $referenceSourceId)
                ->delete();

            DB::table('partner_phones')
                ->where('reference_source_id', $referenceSourceId)
                ->update(['reference_source_id' => null]);

            DB::delete('DELETE FROM reference_sources WHERE id = ?', [$referenceSourceId]);
        });
    }

    /**
     * @param  callable(): void  $callback
     */
    private static function runWithMysqlRetry(callable $callback, int $attempts = 3): void
    {
        $last = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $callback();

                return;
            } catch (PDOException $e) {
                $last = $e;

                if (! self::isMysqlReprepareError($e) || $i === $attempts - 1) {
                    throw $e;
                }

                DB::reconnect();
            }
        }

        if ($last) {
            throw $last;
        }
    }

    private static function isMysqlReprepareError(PDOException $e): bool
    {
        return str_contains($e->getMessage(), '1615')
            || str_contains($e->getMessage(), 'needs to be re-prepared');
    }

    /** @return int Количество созданных/обновлённых зеркал */
    public static function syncAllForOwner(int $ownerUserId): int
    {
        if (! self::isActive()) {
            return 0;
        }

        $count = 0;

        Source::query()
            ->where('user_id', $ownerUserId)
            ->orderBy('id')
            ->each(function (Source $source) use (&$count) {
                self::sync($source);
                $count++;
            });

        return $count;
    }
}
