<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class ReferenceSource extends Model
{
    protected $fillable = [
        'levelion_source_id',
        'local_source_id',
        'name',
        'crm_city_id',
        'city_name',
        'superpart_partner_id',
        'available_for_superpart',
        'shared_with_all_partners',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'available_for_superpart' => 'boolean',
            'shared_with_all_partners' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeAvailableForSuperpart($query)
    {
        return $query->where('available_for_superpart', true);
    }

    /** Источник можно передать в CRM (есть ID и флаг каталога SuperPart). */
    public function scopePushableToCrm($query)
    {
        return $query
            ->availableForSuperpart()
            ->whereNotNull('levelion_source_id')
            ->where('levelion_source_id', '>', 0);
    }

    /** Источник из CRM без локального зеркала в SuperPart. */
    public function scopeFromCrmCatalog(Builder $query, int $partnerUserId): Builder
    {
        return $query
            ->availableForSuperpart()
            ->whereNull('local_source_id')
            ->whereNotNull('levelion_source_id')
            ->where('levelion_source_id', '>', 0)
            ->where(function (Builder $partnerScope) use ($partnerUserId) {
                $partnerScope->whereNull('superpart_partner_id')
                    ->orWhere('superpart_partner_id', $partnerUserId);
            });
    }

    /** Источник доступен в форме заявки: из CRM или зеркало локального sources. */
    public function scopeSelectableForOrderForm($query)
    {
        return $query->availableForSuperpart()->where(function ($selectable) {
            $selectable->pushableToCrm()
                ->orWhereNotNull('local_source_id');
        });
    }

    /** Уникальный ID источника как в CRM (для выплат / раздела «Источники»). */
    public function crmId(): int
    {
        if ($this->levelion_source_id !== null && (int) $this->levelion_source_id > 0) {
            return (int) $this->levelion_source_id;
        }

        return (int) $this->id;
    }

    public function canPushToCrm(): bool
    {
        return $this->available_for_superpart
            && $this->levelion_source_id !== null
            && (int) $this->levelion_source_id > 0;
    }

    public function isSelectableForOrderForm(): bool
    {
        if (! $this->available_for_superpart) {
            return false;
        }

        if ($this->local_source_id !== null) {
            return true;
        }

        return $this->levelion_source_id !== null && (int) $this->levelion_source_id > 0;
    }

    /** Источники партнёра из SuperPart (созданы в разделе «Источники»). */
    public function scopeFromPartnerPortal(Builder $query, int $ownerId): Builder
    {
        return $query
            ->availableForSuperpart()
            ->whereNotNull('local_source_id')
            ->where('superpart_partner_id', $ownerId);
    }

    /**
     * Источники для формы заявки с учётом партнёра и ограничений менеджера.
     */
    public static function queryForOrderForm(User $user): Builder
    {
        $allowedIds = \App\Support\PartnerSourceAccess::accessibleSourceIdsFor($user);

        if ($user->hasElevatedAccess()) {
            return static::query()
                ->availableForSuperpart()
                ->where(function (Builder $sources) {
                    $sources->whereNotNull('local_source_id')
                        ->orWhere(function (Builder $crm) {
                            $crm->whereNull('local_source_id')
                                ->whereNotNull('levelion_source_id')
                                ->where('levelion_source_id', '>', 0);
                        });
                })
                ->orderBy('name');
        }

        if ($allowedIds->isEmpty()) {
            return static::query()->whereRaw('0 = 1');
        }

        return static::query()
            ->whereIn('id', $allowedIds->all())
            ->availableForSuperpart()
            ->orderBy('name');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superpart_partner_id');
    }

    public function localSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'local_source_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'reference_source_id');
    }

    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_allowed_reference_sources');
    }

    /**
     * Источник доступен партнёру: назначен в user_allowed_reference_sources (или legacy superpart_partner_id).
     */
    public function isVisibleToPartnerUserId(int $partnerUserId): bool
    {
        if ($this->shared_with_all_partners) {
            return true;
        }

        if ((int) $this->superpart_partner_id === $partnerUserId) {
            return true;
        }

        return $this->allowedUsers()
            ->where('users.id', $partnerUserId)
            ->where('users.role', User::ROLE_PARTNER)
            ->exists();
    }

    /**
     * Найти или создать зеркало CRM-источника для атрибуции заявки.
     * Не-SuperPart РК (Листовка и т.п.) храним для метки и синка, но available=false /
     * shared=false — партнёры их не видят в списках (см. PartnerSourceAccess).
     */
    public static function ensureFromCrmSource(int $crmSourceId, ?string $name = null): self
    {
        $crmSourceId = (int) $crmSourceId;
        if ($crmSourceId < 1) {
            throw new \InvalidArgumentException('crmSourceId must be >= 1');
        }

        $ref = static::query()->where('levelion_source_id', $crmSourceId)->first();
        $resolvedName = is_string($name) && trim($name) !== '' ? trim($name) : null;

        if ($ref) {
            $dirty = false;
            if ($resolvedName !== null && $ref->name !== $resolvedName) {
                $ref->name = $resolvedName;
                $dirty = true;
            }
            // Исторические зеркала с shared=true + avail=false не должны «протекать» в ACL.
            if (! $ref->available_for_superpart && $ref->shared_with_all_partners) {
                $ref->shared_with_all_partners = false;
                $dirty = true;
            }
            if ($dirty) {
                $ref->synced_at = now();
                $ref->save();
            }

            return $ref;
        }

        return static::query()->create([
            'levelion_source_id' => $crmSourceId,
            'name' => $resolvedName ?? ('Источник #'.$crmSourceId),
            'available_for_superpart' => false,
            'shared_with_all_partners' => false,
            'synced_at' => now(),
        ]);
    }

    public static function existsRuleVisibleToPartnerUser(int $partnerUserId): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('reference_sources', 'id')->where(function ($query) use ($partnerUserId) {
            $query->where('available_for_superpart', true)
                ->where(function ($sources) use ($partnerUserId) {
                    $sources->where('shared_with_all_partners', true)
                        ->orWhere('superpart_partner_id', $partnerUserId)
                        ->orWhereIn('id', function ($sub) use ($partnerUserId) {
                            $sub->select('reference_source_id')
                                ->from('user_allowed_reference_sources')
                                ->where('user_id', $partnerUserId);
                        });
                });
        });
    }
}
